<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\Validation\ValidationException;
use App\Core\Validation\Validator;
use App\Middleware\Authenticate;
use App\Services\Auth\AuthService;
use App\Services\Auth\LoginResult;

/**
 * Connexion, second facteur et déconnexion.
 */
final class AuthController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly AuthService $auth,
        private readonly Session $session,
    ) {
    }

    public function showLogin(Request $request): Response
    {
        return $this->responder->view($request, 'auth/login');
    }

    public function login(Request $request): Response
    {
        $data = Validator::validate($request->all(), [
            'email' => 'required|email|max:254',
            'password' => 'required|string|max:128',
        ], ['email' => 'adresse e-mail', 'password' => 'mot de passe']);
        $email = (string) $data['email'];
        $result = $this->auth->attempt($email, (string) $data['password'], $request->ip());

        return match ($result->status) {
            LoginResult::OK => $this->finishLogin($request, (int) $result->userId),
            LoginResult::TWO_FACTOR => $this->startTwoFactor((int) $result->userId),
            LoginResult::LOCKED => throw ValidationException::withMessage('email', self::lockedMessage($result->retryAfter), ['email' => $email]),
            LoginResult::SUSPENDED => throw ValidationException::withMessage('email', 'Ce compte est suspendu. Contactez le support.', ['email' => $email]),
            LoginResult::PENDING => throw ValidationException::withMessage('email', 'Ce compte n\'est pas encore activé : acceptez l\'invitation reçue par e-mail.', ['email' => $email]),
            // Message identique que l'adresse existe ou non.
            default => throw ValidationException::withMessage('email', 'Adresse e-mail ou mot de passe incorrect.', ['email' => $email]),
        };
    }

    public function showTwoFactor(Request $request): Response
    {
        if ($this->auth->pendingTwoFactorUser($this->session) === null) {
            return $this->responder->flash('info', 'Votre session a expiré, reconnectez-vous.')->redirect('/login');
        }

        return $this->responder->view($request, 'auth/two-factor');
    }

    public function twoFactor(Request $request): Response
    {
        $user = $this->auth->pendingTwoFactorUser($this->session);
        if ($user === null) {
            return $this->responder->flash('info', 'Votre session a expiré, reconnectez-vous.')->redirect('/login');
        }
        $data = Validator::validate($request->all(), ['code' => 'required|string|max:20'], ['code' => 'code']);
        $result = $this->auth->completeTwoFactor($this->session, $user, (string) $data['code'], $request->ip());

        return match ($result->status) {
            LoginResult::OK => $this->redirectAfterLogin(),
            LoginResult::LOCKED => throw ValidationException::withMessage('code', self::lockedMessage($result->retryAfter)),
            default => throw ValidationException::withMessage('code', 'Code invalide ou déjà utilisé.'),
        };
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($this->session, $request->ip());

        return $this->responder->flash('success', 'Vous êtes déconnecté.')->redirect('/login');
    }

    private function finishLogin(Request $request, int $userId): Response
    {
        $this->auth->login($this->session, $userId, $request->ip());

        return $this->redirectAfterLogin();
    }

    private function startTwoFactor(int $userId): Response
    {
        $this->auth->beginTwoFactor($this->session, $userId);

        return $this->responder->redirect('/login/2fa');
    }

    private function redirectAfterLogin(): Response
    {
        $intended = $this->session->pull(Authenticate::INTENDED);

        return $this->responder->redirect(is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//') ? strtok($intended, '?') ?: '/console' : '/console');
    }

    private static function lockedMessage(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return 'Trop de tentatives. Réessayez dans ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '') . '.';
    }
}
