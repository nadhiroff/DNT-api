<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Config;
use App\Core\Http\UrlGenerator;
use App\Core\Security\Csrf;
use App\Core\Security\PasswordHasher;
use App\Core\Security\PasswordPolicy;
use App\Core\Session\DatabaseSessionHandler;
use App\Core\Session\Session;
use App\Core\Validation\ValidationException;
use App\Repositories\PlanRepository;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\LoginEventService;
use App\Services\Mail\PlatformMailer;

/**
 * Comptes de la plateforme : inscription, connexion (avec 2FA), vérification d'e-mail,
 * réinitialisation et changement de mot de passe, suppression du compte.
 *
 * @phpstan-import-type UserRow from UserRepository
 */
final class AuthService
{
    private const string PENDING_2FA = '_2fa_pending';

    private const int PENDING_2FA_TTL = 300;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PlanRepository $plans,
        private readonly SessionRepository $sessions,
        private readonly PasswordHasher $hasher,
        private readonly PasswordPolicy $policy,
        private readonly LoginThrottle $throttle,
        private readonly UserTokenService $tokens,
        private readonly TwoFactorService $twoFactor,
        private readonly PlatformMailer $mailer,
        private readonly AuditService $audit,
        private readonly UrlGenerator $urls,
        private readonly Csrf $csrf,
        private readonly Config $config,
        private readonly LoginEventService $loginEvents,
    ) {
    }

    /**
     * Inscription. Si l'adresse est déjà utilisée, on n'en dit rien à l'écran (anti-énumération) :
     * le titulaire reçoit un e-mail l'informant de la tentative.
     *
     * @return int|null identifiant du nouveau compte, null si l'adresse existait déjà
     *
     * @throws ValidationException mot de passe refusé par la politique
     */
    public function register(string $name, string $email, string $password, string $ip): ?int
    {
        $errors = $this->policy->validate($password, $email, $name);
        if ($errors !== []) {
            throw new ValidationException(['password' => $errors], ['name' => $name, 'email' => $email]);
        }
        $existing = $this->users->findByEmail($email);
        if ($existing !== null) {
            $this->hasher->dummyVerify($password);
            $this->mailer->send($existing['email'], 'Tentative d\'inscription avec votre adresse', 'account-exists', [
                'name' => $existing['name'],
                'loginUrl' => $this->urls->absolute('/login'),
                'resetUrl' => $this->urls->absolute('/forgot-password'),
            ]);

            return null;
        }
        $userId = $this->users->create([
            'email' => $email,
            'password_hash' => $this->hasher->hash($password),
            'name' => $name,
            'role' => 'user',
            'plan_id' => $this->plans->defaultPlanId(),
            'status' => 'active',
            'email_verified' => false,
        ]);
        $this->audit->record('user.registered', $userId, 'user:' . $userId, $ip);
        $this->sendVerification($userId);

        return $userId;
    }

    public function attempt(string $email, string $password, string $ip): LoginResult
    {
        $locked = $this->throttle->lockedFor($email, $ip);
        if ($locked > 0) {
            $this->loginEvents->record('locked', null, $email, $ip);

            return LoginResult::locked($locked);
        }
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            $this->hasher->dummyVerify($password);
        }
        if ($user === null || !$this->hasher->verify($password, $user['password_hash'])) {
            $this->throttle->failure($email, $ip);
            $this->audit->record('auth.login_failed', $user['id'] ?? null, $user !== null ? 'user:' . $user['id'] : null, $ip);
            $this->loginEvents->record('login_failed', $user['id'] ?? null, $email, $ip);

            return LoginResult::invalid();
        }
        $this->throttle->success($email, $ip);
        if ($user['status'] === 'pending') {
            return LoginResult::pending();
        }
        if ($user['status'] === 'suspended') {
            $this->audit->record('auth.login_suspended', $user['id'], 'user:' . $user['id'], $ip);

            return LoginResult::suspended();
        }
        if ($this->hasher->needsRehash($user['password_hash'])) {
            $this->users->updatePassword($user['id'], $this->hasher->hash($password));
        }

        return $user['totp_enabled_at'] !== null ? LoginResult::twoFactor($user['id']) : LoginResult::ok($user['id']);
    }

    /**
     * Ouvre la session authentifiée (nouvel identifiant de session et nouveau jeton CSRF).
     */
    public function login(Session $session, int $userId, string $ip): void
    {
        $session->remove(self::PENDING_2FA);
        $session->login($userId);
        $this->csrf->rotate();
        $this->users->recordLogin($userId, $ip);
        $this->audit->record('auth.login', $userId, 'user:' . $userId, $ip);
        $this->loginEvents->record('login', $userId, $this->users->findById($userId)['email'] ?? null, $ip);
    }

    public function beginTwoFactor(Session $session, int $userId): void
    {
        // Nouvel identifiant dès cette étape : le mot de passe est déjà validé.
        $session->regenerate();
        $session->set(self::PENDING_2FA, ['user_id' => $userId, 'at' => time()]);
    }

    /**
     * Utilisateur dont le mot de passe est validé et qui doit fournir son second facteur.
     *
     * @return UserRow|null
     */
    public function pendingTwoFactorUser(Session $session): ?array
    {
        $pending = $session->get(self::PENDING_2FA);
        if (!is_array($pending) || !is_int($pending['user_id'] ?? null) || !is_int($pending['at'] ?? null)) {
            return null;
        }
        if (time() - $pending['at'] > self::PENDING_2FA_TTL) {
            $session->remove(self::PENDING_2FA);

            return null;
        }

        return $this->users->findById($pending['user_id']);
    }

    /**
     * @param UserRow $user
     */
    public function completeTwoFactor(Session $session, array $user, string $code, string $ip): LoginResult
    {
        $key = '2fa:' . $user['id'];
        $locked = $this->throttle->lockedFor($key, $ip);
        if ($locked > 0) {
            return LoginResult::locked($locked);
        }
        if (!$this->twoFactor->verify($user, $code)) {
            $this->throttle->failure($key, $ip);
            $this->audit->record('auth.2fa_failed', $user['id'], 'user:' . $user['id'], $ip);
            $this->loginEvents->record('2fa_failed', $user['id'], $user['email'], $ip);

            return LoginResult::invalid();
        }
        $this->throttle->success($key, $ip);
        $this->login($session, $user['id'], $ip);

        return LoginResult::ok($user['id']);
    }

    public function logout(Session $session, string $ip): void
    {
        $userId = $session->userId();
        $session->invalidate();
        $this->csrf->rotate();
        if ($userId !== null) {
            $this->audit->record('auth.logout', $userId, 'user:' . $userId, $ip);
            $this->loginEvents->record('logout', $userId, $this->users->findById($userId)['email'] ?? null, $ip);
        }
    }

    public function sendVerification(int $userId): void
    {
        $user = $this->users->findById($userId);
        if ($user === null || $user['email_verified_at'] !== null) {
            return;
        }
        $token = $this->tokens->issue($userId, UserTokenService::EMAIL_VERIFICATION);
        $this->mailer->send($user['email'], 'Confirmez votre adresse e-mail', 'verify-email', [
            'name' => $user['name'],
            'url' => $this->urls->absolute('/email/verify/' . $token),
        ]);
    }

    public function verifyEmail(string $token, string $ip): ?int
    {
        $userId = $this->tokens->consume(UserTokenService::EMAIL_VERIFICATION, $token);
        if ($userId !== null) {
            $this->users->markEmailVerified($userId);
            $this->audit->record('user.email_verified', $userId, 'user:' . $userId, $ip);
        }

        return $userId;
    }

    /**
     * Envoie un lien de réinitialisation si le compte existe ; ne révèle jamais s'il existe.
     */
    public function sendPasswordReset(string $email, string $ip): void
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || $user['status'] !== 'active') {
            return;
        }
        $token = $this->tokens->issue($user['id'], UserTokenService::PASSWORD_RESET);
        $this->mailer->send($user['email'], 'Réinitialisation de votre mot de passe', 'reset-password', [
            'name' => $user['name'],
            'url' => $this->urls->absolute('/reset-password/' . $token),
        ]);
        $this->audit->record('auth.password_reset_requested', $user['id'], 'user:' . $user['id'], $ip);
    }

    public function isResetTokenValid(string $token): bool
    {
        return $this->tokens->isValid(UserTokenService::PASSWORD_RESET, $token);
    }

    /**
     * Réinitialise le mot de passe et ferme toutes les sessions ouvertes du compte.
     *
     * @throws ValidationException
     */
    public function resetPassword(string $token, string $password, string $ip): bool
    {
        $ownerId = $this->tokens->peek(UserTokenService::PASSWORD_RESET, $token);
        $user = $ownerId !== null ? $this->users->findById($ownerId) : null;
        if ($user === null) {
            return false;
        }
        // Politique vérifiée avant consommation : un mot de passe refusé ne brûle pas le lien.
        $errors = $this->policy->validate($password, $user['email'], $user['name']);
        if ($errors !== []) {
            throw new ValidationException(['password' => $errors]);
        }
        if ($this->tokens->consume(UserTokenService::PASSWORD_RESET, $token) !== $user['id']) {
            return false;
        }
        $this->applyNewPassword($user, $password, null, $ip, 'auth.password_reset');
        // Le lien prouve l'accès à la boîte mail : l'adresse est donc vérifiée.
        $this->users->markEmailVerified($user['id']);

        return true;
    }

    /**
     * @return list<string> erreurs de politique (vide si acceptable)
     */
    public function passwordErrors(string $password, ?string $email = null, ?string $name = null): array
    {
        return $this->policy->validate($password, $email, $name);
    }

    /**
     * @param UserRow $user
     *
     * @throws ValidationException
     */
    public function changePassword(array $user, string $current, string $new, Session $session, string $ip): void
    {
        if (!$this->hasher->verify($current, $user['password_hash'])) {
            throw ValidationException::withMessage('current_password', 'Le mot de passe actuel est incorrect.');
        }
        $this->applyNewPassword($user, $new, $session, $ip, 'auth.password_changed');
    }

    /**
     * @param UserRow $user
     */
    public function checkPassword(array $user, string $password): bool
    {
        return $this->hasher->verify($password, $user['password_hash']);
    }

    /**
     * @param UserRow $user
     *
     * @throws ValidationException
     */
    public function deleteAccount(array $user, string $password, Session $session, string $ip): void
    {
        if (!$this->hasher->verify($password, $user['password_hash'])) {
            throw ValidationException::withMessage('delete_password', 'Mot de passe incorrect.');
        }
        if ($user['role'] === 'superadmin' && $this->users->countByRole('superadmin') <= 1) {
            throw ValidationException::withMessage('delete_password', 'Impossible de supprimer le dernier compte superadmin.');
        }
        $this->audit->record('user.deleted', $user['id'], 'user:' . $user['id'], $ip, ['email' => $user['email']]);
        $this->users->delete($user['id']);
        $session->invalidate();
        $this->csrf->rotate();
    }

    /**
     * @param UserRow $user
     *
     * @throws ValidationException
     */
    private function applyNewPassword(array $user, string $password, ?Session $keep, string $ip, string $action): void
    {
        $errors = $this->policy->validate($password, $user['email'], $user['name']);
        if ($errors !== []) {
            throw new ValidationException(['password' => $errors]);
        }
        $this->users->updatePassword($user['id'], $this->hasher->hash($password));
        $keepId = $keep !== null && $keep->id() !== '' ? DatabaseSessionHandler::hashId($keep->id()) : null;
        $this->sessions->revokeAllExcept($user['id'], $keepId);
        $this->audit->record($action, $user['id'], 'user:' . $user['id'], $ip);
        $this->mailer->send($user['email'], 'Votre mot de passe a été modifié', 'password-changed', [
            'name' => $user['name'],
            'resetUrl' => $this->urls->absolute('/forgot-password'),
            'appName' => $this->config->string('app.name', 'NovaBase'),
        ]);
    }
}
