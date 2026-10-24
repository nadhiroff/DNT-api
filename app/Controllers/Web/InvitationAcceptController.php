<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\Validation\ValidationException;
use App\Core\Validation\Validator;
use App\Services\Auth\AuthService;
use App\Services\InvitationService;

/**
 * Acceptation d'une invitation : l'invité choisit son nom et son mot de passe, puis est connecté.
 */
final class InvitationAcceptController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly InvitationService $invitations,
        private readonly AuthService $auth,
        private readonly Session $session,
    ) {
    }

    public function show(Request $request): Response
    {
        $invitation = $this->invitations->findPending((string) $request->route('token'));
        if ($invitation === null) {
            return $this->invalid($request);
        }

        return $this->responder->view($request, 'auth/invitation', ['invitation' => $invitation, 'token' => (string) $request->route('token')]);
    }

    public function accept(Request $request): Response
    {
        $token = (string) $request->route('token');
        $data = Validator::validate($request->all(), [
            'name' => 'required|string|min:2|max:120',
            'password' => 'required|string|max:128',
            'password_confirmation' => 'required|same:password',
            'terms' => 'required|boolean',
        ], ['name' => 'nom', 'password' => 'mot de passe', 'password_confirmation' => 'confirmation', 'terms' => 'conditions d\'utilisation']);
        if ($data['terms'] !== true) {
            throw ValidationException::withMessage('terms', 'Vous devez accepter les conditions d\'utilisation.', ['name' => $data['name']]);
        }
        $userId = $this->invitations->accept($token, (string) $data['name'], (string) $data['password'], $request->ip());
        if ($userId === null) {
            return $this->invalid($request);
        }
        if ($this->session->userId() !== null) {
            $this->auth->logout($this->session, $request->ip());
        }
        $this->auth->login($this->session, $userId, $request->ip());

        return $this->responder->flash('success', 'Bienvenue ! Votre compte est activé.')->redirect('/console');
    }

    private function invalid(Request $request): Response
    {
        return $this->responder->view($request, 'auth/message', [
            'title' => 'Invitation invalide ou expirée',
            'message' => 'Ce lien d\'invitation n\'est plus valable (déjà utilisé, révoqué ou expiré après 7 jours). Demandez un nouveau lien à la personne qui vous a invité.',
            'tone' => 'error',
            'actionLabel' => 'Retour à l\'accueil',
            'actionPath' => '/',
        ], 410);
    }
}
