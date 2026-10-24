<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Services\Auth\AuthService;
use App\Services\RateLimiter;

final class EmailVerificationController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly AuthService $auth,
        private readonly RateLimiter $limiter,
        private readonly Session $session,
    ) {
    }

    /**
     * Page d'attente pour un compte connecté dont l'adresse n'est pas encore confirmée.
     */
    public function notice(Request $request): Response
    {
        $user = CurrentUser::from($request);
        if ($user['email_verified_at'] !== null) {
            return $this->responder->redirect('/console');
        }

        return $this->responder->view($request, 'auth/verify-email', ['email' => $user['email']]);
    }

    public function resend(Request $request): Response
    {
        $user = CurrentUser::from($request);
        if (!$this->limiter->attempt('verify-resend:' . $user['id'], 3, 600)) {
            return $this->responder->flash('error', 'Vous avez demandé plusieurs liens récemment. Patientez quelques minutes.')->redirect('/email/verify');
        }
        $this->auth->sendVerification($user['id']);

        return $this->responder->flash('success', 'Un nouveau lien vient de vous être envoyé.')->redirect('/email/verify');
    }

    public function verify(Request $request): Response
    {
        $userId = $this->auth->verifyEmail((string) $request->route('token'), $request->ip());
        if ($userId === null) {
            return $this->responder->view($request, 'auth/message', [
                'title' => 'Lien invalide ou expiré',
                'message' => 'Ce lien de confirmation n\'est plus valable (validité 1 h, usage unique). Connectez-vous pour en recevoir un nouveau.',
                'tone' => 'error',
                'actionLabel' => 'Se connecter',
                'actionPath' => '/login',
            ], 410);
        }
        if ($this->session->userId() === $userId) {
            return $this->responder->flash('success', 'Adresse e-mail confirmée. Bienvenue !')->redirect('/console');
        }

        return $this->responder->flash('success', 'Adresse e-mail confirmée. Vous pouvez maintenant vous connecter.')->redirect('/login');
    }
}
