<?php

declare(strict_types=1);

if (!defined('NOVABASE_ROOT')) {
    http_response_code(404);
    exit;
}

use App\Controllers\Api\HealthController;
use App\Controllers\Web\AccountController;
use App\Controllers\Web\AuthController;
use App\Controllers\Web\ConsoleController;
use App\Controllers\Web\DevMailController;
use App\Controllers\Web\EmailVerificationController;
use App\Controllers\Web\PageController;
use App\Controllers\Web\PasswordResetController;
use App\Controllers\Web\RegisterController;
use App\Core\Http\Router;
use App\Middleware\Authenticate;
use App\Middleware\Csrf;
use App\Middleware\EnsureEmailVerified;
use App\Middleware\EnsureTwoFactorEnrolled;
use App\Middleware\RedirectIfAuthenticated;
use App\Middleware\StartSession;

return static function (Router $router): void {
    // Supervision (sans session, sans état).
    $router->get('/health', [HealthController::class, 'show'], name: 'health');

    // Espace web : sessions + CSRF.
    $router->group('/', [StartSession::class, Csrf::class], static function (Router $web): void {
        $web->get('/', [PageController::class, 'home'], name: 'home');
        $web->get('/mentions-legales', [PageController::class, 'legal'], name: 'legal.mentions');
        $web->get('/confidentialite', [PageController::class, 'legal'], name: 'legal.privacy');
        $web->get('/conditions', [PageController::class, 'legal'], name: 'legal.terms');

        // Visiteurs uniquement.
        $web->group('/', [RedirectIfAuthenticated::class], static function (Router $guest): void {
            $guest->get('/login', [AuthController::class, 'showLogin'], name: 'login');
            $guest->post('/login', [AuthController::class, 'login']);
            $guest->get('/login/2fa', [AuthController::class, 'showTwoFactor'], name: 'login.2fa');
            $guest->post('/login/2fa', [AuthController::class, 'twoFactor']);
            $guest->get('/register', [RegisterController::class, 'show'], name: 'register');
            $guest->post('/register', [RegisterController::class, 'register']);
            $guest->get('/register/done', [RegisterController::class, 'done'], name: 'register.done');
            $guest->get('/forgot-password', [PasswordResetController::class, 'showRequest'], name: 'password.request');
            $guest->post('/forgot-password', [PasswordResetController::class, 'sendLink']);
            $guest->get('/reset-password/{token:[a-f0-9]{64}}', [PasswordResetController::class, 'showReset'], name: 'password.reset');
            $guest->post('/reset-password/{token:[a-f0-9]{64}}', [PasswordResetController::class, 'reset']);
        });

        // Lien reçu par e-mail : valable connecté ou non.
        $web->get('/email/verify/{token:[a-f0-9]{64}}', [EmailVerificationController::class, 'verify'], name: 'verification.verify');

        // Utilisateurs connectés.
        $web->group('/', [Authenticate::class], static function (Router $auth): void {
            $auth->post('/logout', [AuthController::class, 'logout'], name: 'logout');
            $auth->get('/email/verify', [EmailVerificationController::class, 'notice'], name: 'verification.notice');
            $auth->post('/email/verify/resend', [EmailVerificationController::class, 'resend'], name: 'verification.resend');

            // Enrôlement 2FA : accessible avant l'activation obligatoire.
            $auth->get('/account/2fa', [AccountController::class, 'showTwoFactorSetup'], name: 'account.2fa');
            $auth->post('/account/2fa', [AccountController::class, 'confirmTwoFactor']);
            $auth->get('/account/2fa/recovery-codes', [AccountController::class, 'showRecoveryCodes'], name: 'account.2fa.codes');

            $auth->group('/', [EnsureTwoFactorEnrolled::class], static function (Router $secure): void {
                $secure->get('/account', [AccountController::class, 'index'], name: 'account');
                $secure->post('/account/profile', [AccountController::class, 'updateProfile']);
                $secure->post('/account/password', [AccountController::class, 'updatePassword']);
                $secure->post('/account/2fa/disable', [AccountController::class, 'disableTwoFactor']);
                $secure->post('/account/2fa/recovery-codes', [AccountController::class, 'regenerateRecoveryCodes']);
                $secure->post('/account/sessions/revoke-others', [AccountController::class, 'revokeOtherSessions']);
                $secure->post('/account/sessions/{id:[a-f0-9]{64}}/revoke', [AccountController::class, 'revokeSession']);
                $secure->post('/account/delete', [AccountController::class, 'delete']);

                $secure->group('/', [EnsureEmailVerified::class], static function (Router $console): void {
                    $console->get('/console', [ConsoleController::class, 'dashboard'], name: 'console');
                });
            });
        });

        // Boîte de réception de développement (refusée hors APP_ENV=local + MAIL_DRIVER=log).
        $web->get('/_dev/mail', [DevMailController::class, 'index'], name: 'dev.mail');
        $web->get('/_dev/mail/{id:[0-9]{8}-[0-9]{6}-[0-9]{6}[a-f0-9]{4}}', [DevMailController::class, 'show']);
    });
};
