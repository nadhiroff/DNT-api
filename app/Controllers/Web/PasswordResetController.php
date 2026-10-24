<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Validation\ValidationException;
use App\Core\Validation\Validator;
use App\Services\Auth\AuthService;
use App\Services\RateLimiter;

/**
 * Mot de passe oublié : demande de lien puis choix d'un nouveau mot de passe.
 */
final class PasswordResetController
{
    private const string SENT = 'Si un compte correspond à cette adresse, un lien de réinitialisation vient d\'être envoyé. Il est valable 1 heure.';

    public function __construct(
        private readonly Responder $responder,
        private readonly AuthService $auth,
        private readonly RateLimiter $limiter,
    ) {
    }

    public function showRequest(Request $request): Response
    {
        return $this->responder->view($request, 'auth/forgot-password');
    }

    public function sendLink(Request $request): Response
    {
        $data = Validator::validate($request->all(), ['email' => 'required|email|max:254'], ['email' => 'adresse e-mail']);
        $email = (string) $data['email'];
        $allowed = $this->limiter->attempt('forgot:ip:' . $request->ip(), 10, 3600)
            && $this->limiter->attempt('forgot:email:' . $email, 3, 3600);
        if (!$allowed) {
            throw ValidationException::withMessage('email', 'Trop de demandes. Réessayez dans une heure.', ['email' => $email]);
        }
        $this->auth->sendPasswordReset($email, $request->ip());

        return $this->responder->flash('success', self::SENT)->redirect('/forgot-password');
    }

    public function showReset(Request $request): Response
    {
        $token = (string) $request->route('token');
        if (!$this->auth->isResetTokenValid($token)) {
            return $this->invalid($request);
        }

        return $this->responder->view($request, 'auth/reset-password', ['token' => $token]);
    }

    public function reset(Request $request): Response
    {
        $token = (string) $request->route('token');
        $data = Validator::validate($request->all(), [
            'password' => 'required|string|max:128',
            'password_confirmation' => 'required|same:password',
        ], ['password' => 'nouveau mot de passe', 'password_confirmation' => 'confirmation']);
        if (!$this->auth->resetPassword($token, (string) $data['password'], $request->ip())) {
            return $this->invalid($request);
        }

        return $this->responder->flash('success', 'Mot de passe modifié. Toutes vos sessions ont été fermées : connectez-vous avec le nouveau mot de passe.')->redirect('/login');
    }

    private function invalid(Request $request): Response
    {
        return $this->responder->view($request, 'auth/message', [
            'title' => 'Lien invalide ou expiré',
            'message' => 'Ce lien de réinitialisation n\'est plus valable (validité 1 h, usage unique). Faites une nouvelle demande.',
            'tone' => 'error',
            'actionLabel' => 'Nouvelle demande',
            'actionPath' => '/forgot-password',
        ], 410);
    }
}
