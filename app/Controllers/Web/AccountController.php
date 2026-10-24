<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Config;
use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Session\DatabaseSessionHandler;
use App\Core\Session\Session;
use App\Core\Validation\ValidationException;
use App\Core\Validation\Validator;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\Auth\AuthService;
use App\Services\Auth\TwoFactorService;

/**
 * Profil : nom, mot de passe, double authentification, sessions actives, suppression du compte.
 */
final class AccountController
{
    private const string RECOVERY_FLASH = 'recovery_codes';

    public function __construct(
        private readonly Responder $responder,
        private readonly AuthService $auth,
        private readonly TwoFactorService $twoFactor,
        private readonly UserRepository $users,
        private readonly SessionRepository $sessions,
        private readonly AuditService $audit,
        private readonly Session $session,
        private readonly Config $config,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $currentId = $this->session->id() !== '' ? DatabaseSessionHandler::hashId($this->session->id()) : '';

        return $this->responder->view($request, 'account/index', [
            'user' => $user,
            'sessions' => $this->sessions->activeForUser($user['id'], $this->config->int('security.session.idle_lifetime', 7200)),
            'currentSessionId' => $currentId,
            'recoveryRemaining' => $user['totp_enabled_at'] !== null ? $this->twoFactor->remainingRecoveryCodes($user['id']) : 0,
            'twoFactorRequired' => TwoFactorService::isRequiredFor($user['role']),
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $data = Validator::validate($request->all(), ['name' => 'required|string|min:2|max:120'], ['name' => 'nom']);
        $this->users->updateName($user['id'], (string) $data['name']);

        return $this->responder->flash('success', 'Profil mis à jour.')->redirect('/account');
    }

    public function updatePassword(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $data = Validator::validate($request->all(), [
            'current_password' => 'required|string|max:128',
            'password' => 'required|string|max:128',
            'password_confirmation' => 'required|same:password',
        ], ['current_password' => 'mot de passe actuel', 'password' => 'nouveau mot de passe', 'password_confirmation' => 'confirmation']);
        $this->auth->changePassword($user, (string) $data['current_password'], (string) $data['password'], $this->session, $request->ip());

        return $this->responder->flash('success', 'Mot de passe modifié. Vos autres sessions ont été fermées.')->redirect('/account');
    }

    public function showTwoFactorSetup(Request $request): Response
    {
        $user = CurrentUser::from($request);
        if ($user['totp_enabled_at'] !== null) {
            return $this->responder->redirect('/account');
        }
        $secret = $this->twoFactor->pendingSecret($this->session);

        return $this->responder->view($request, 'account/two-factor-setup', [
            'user' => $user,
            'secret' => $secret,
            'uri' => $this->twoFactor->provisioningUri($secret, $user['email'], $this->config->string('security.totp_issuer', 'NovaBase')),
            'required' => TwoFactorService::isRequiredFor($user['role']),
        ]);
    }

    public function confirmTwoFactor(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $data = Validator::validate($request->all(), ['code' => 'required|string|max:10'], ['code' => 'code']);
        $codes = $this->twoFactor->confirmSetup($user['id'], $this->session, (string) $data['code']);
        if ($codes === null) {
            throw ValidationException::withMessage('code', 'Code incorrect. Vérifiez que l\'heure de votre téléphone est à jour.');
        }
        $this->audit->record('auth.2fa_enabled', $user['id'], 'user:' . $user['id'], $request->ip());
        $this->session->flash(self::RECOVERY_FLASH, $codes);

        return $this->responder->flash('success', 'Double authentification activée.')->redirect('/account/2fa/recovery-codes');
    }

    public function showRecoveryCodes(Request $request): Response
    {
        $codes = $this->session->getFlash(self::RECOVERY_FLASH);
        if (!is_array($codes) || $codes === []) {
            return $this->responder->redirect('/account');
        }

        return $this->responder->view($request, 'account/recovery-codes', ['codes' => array_values(array_filter($codes, 'is_string'))]);
    }

    public function regenerateRecoveryCodes(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $this->requirePassword($user, $request, 'recovery_password');
        if ($user['totp_enabled_at'] === null) {
            throw HttpException::badRequest('La double authentification n\'est pas active.');
        }
        $this->session->flash(self::RECOVERY_FLASH, $this->twoFactor->regenerateRecoveryCodes($user['id']));
        $this->audit->record('auth.2fa_recovery_regenerated', $user['id'], 'user:' . $user['id'], $request->ip());

        return $this->responder->redirect('/account/2fa/recovery-codes');
    }

    public function disableTwoFactor(Request $request): Response
    {
        $user = CurrentUser::from($request);
        if (TwoFactorService::isRequiredFor($user['role'])) {
            throw HttpException::forbidden('La double authentification est obligatoire pour votre rôle.');
        }
        $this->requirePassword($user, $request, 'disable_password');
        $this->twoFactor->disable($user['id']);
        $this->audit->record('auth.2fa_disabled', $user['id'], 'user:' . $user['id'], $request->ip());

        return $this->responder->flash('success', 'Double authentification désactivée.')->redirect('/account');
    }

    public function revokeSession(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $id = (string) $request->route('id');
        $current = $this->session->id() !== '' ? DatabaseSessionHandler::hashId($this->session->id()) : '';
        if (hash_equals($current, $id)) {
            throw HttpException::badRequest('Utilisez « Se déconnecter » pour fermer la session en cours.');
        }
        // La condition user_id empêche de révoquer la session d'un autre compte (IDOR).
        $this->sessions->revoke($user['id'], $id);
        $this->audit->record('auth.session_revoked', $user['id'], 'user:' . $user['id'], $request->ip());

        return $this->responder->flash('success', 'Session fermée.')->redirect('/account');
    }

    public function revokeOtherSessions(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $current = $this->session->id() !== '' ? DatabaseSessionHandler::hashId($this->session->id()) : null;
        $count = $this->sessions->revokeAllExcept($user['id'], $current);
        $this->audit->record('auth.sessions_revoked', $user['id'], 'user:' . $user['id'], $request->ip(), ['count' => $count]);

        return $this->responder->flash('success', $count . ' autre(s) session(s) fermée(s).')->redirect('/account');
    }

    public function delete(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $data = Validator::validate($request->all(), [
            'delete_password' => 'required|string|max:128',
            'delete_confirm' => 'required|string',
        ], ['delete_password' => 'mot de passe', 'delete_confirm' => 'confirmation']);
        if ($data['delete_confirm'] !== 'SUPPRIMER') {
            throw ValidationException::withMessage('delete_confirm', 'Tapez SUPPRIMER en majuscules pour confirmer.');
        }
        $this->auth->deleteAccount($user, (string) $data['delete_password'], $this->session, $request->ip());

        return $this->responder->flash('success', 'Votre compte a été supprimé.')->redirect('/');
    }

    /**
     * @param array{password_hash: string} $user
     */
    private function requirePassword(array $user, Request $request, string $field): void
    {
        $password = $request->input($field);
        if (!is_string($password) || !password_verify($password, $user['password_hash'])) {
            throw ValidationException::withMessage($field, 'Mot de passe incorrect.');
        }
    }
}
