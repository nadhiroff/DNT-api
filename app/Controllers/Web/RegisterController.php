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
use App\Services\RateLimiter;

/**
 * Inscription des utilisateurs de la plateforme.
 */
final class RegisterController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly AuthService $auth,
        private readonly RateLimiter $limiter,
        private readonly Session $session,
    ) {
    }

    public function show(Request $request): Response
    {
        return $this->responder->view($request, 'auth/register');
    }

    public function register(Request $request): Response
    {
        $data = Validator::validate($request->all(), [
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:254',
            'password' => 'required|string|max:128',
            'password_confirmation' => 'required|same:password',
            'terms' => 'required|boolean',
        ], ['name' => 'nom', 'email' => 'adresse e-mail', 'password' => 'mot de passe', 'password_confirmation' => 'confirmation', 'terms' => 'conditions d\'utilisation']);
        if ($data['terms'] !== true) {
            throw ValidationException::withMessage('terms', 'Vous devez accepter les conditions d\'utilisation.', ['name' => $data['name'], 'email' => $data['email']]);
        }
        if (!$this->limiter->attempt('register:' . $request->ip(), 5, 3600)) {
            throw ValidationException::withMessage('email', 'Trop d\'inscriptions depuis votre connexion. Réessayez plus tard.', ['name' => $data['name'], 'email' => $data['email']]);
        }

        $this->auth->register((string) $data['name'], (string) $data['email'], (string) $data['password'], $request->ip());
        // Même écran que l'adresse soit nouvelle ou déjà utilisée (anti-énumération).
        $this->session->flash('registered_email', $data['email']);

        return $this->responder->redirect('/register/done');
    }

    public function done(Request $request): Response
    {
        $email = $this->session->getFlash('registered_email');
        if (!is_string($email)) {
            return $this->responder->redirect('/register');
        }

        return $this->responder->view($request, 'auth/register-done', ['email' => $email]);
    }
}
