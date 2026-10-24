<?php

declare(strict_types=1);

if (!defined('NOVABASE_ROOT')) {
    http_response_code(404);
    exit;
}

use App\Controllers\Api\DataApiController;
use App\Controllers\Api\EmailApiController;
use App\Controllers\Api\HealthController;
use App\Controllers\Api\ProjectApiController;
use App\Controllers\Web\AccountController;
use App\Controllers\Web\Admin\AdminController;
use App\Controllers\Web\Admin\EmailSettingsController;
use App\Controllers\Web\ProjectEmailController;
use App\Controllers\Web\SqlEditorController;
use App\Controllers\Web\TableController;
use App\Controllers\Web\Admin\InvitationController;
use App\Controllers\Web\Admin\LoginLogController;
use App\Controllers\Web\Admin\ProjectAdminController;
use App\Controllers\Web\Admin\UserController;
use App\Controllers\Web\InvitationAcceptController;
use App\Controllers\Web\ProjectController;
use App\Controllers\Web\Admin\TestimonialController;
use App\Controllers\Web\MediaController;
use App\Controllers\Web\AuthController;
use App\Controllers\Web\ConsoleController;
use App\Controllers\Web\DevMailController;
use App\Controllers\Web\EmailVerificationController;
use App\Controllers\Web\PageController;
use App\Controllers\Web\PasswordResetController;
use App\Controllers\Web\RegisterController;
use App\Core\Http\Router;
use App\Middleware\ApiKeyAuth;
use App\Middleware\Authenticate;
use App\Middleware\Csrf;
use App\Middleware\EnsureEmailVerified;
use App\Middleware\EnsureTwoFactorEnrolled;
use App\Middleware\RedirectIfAuthenticated;
use App\Middleware\RequireSuperadmin;
use App\Middleware\StartSession;

return static function (Router $router): void {
    // Supervision (sans session, sans état).
    $router->get('/health', [HealthController::class, 'show'], name: 'health');

    // API REST des projets (sans session, authentification par clé).
    $router->group('/api/v1/{ref:[a-z0-9]{20}}', [ApiKeyAuth::class], static function (Router $api): void {
        $api->get('/', [ProjectApiController::class, 'info'], name: 'api.project');
        $api->get('/openapi.json', [ProjectApiController::class, 'openapi'], name: 'api.openapi');
        $api->get('/data/{table:[a-z][a-z0-9_]{0,62}}', [DataApiController::class, 'index']);
        $api->post('/data/{table:[a-z][a-z0-9_]{0,62}}', [DataApiController::class, 'store']);
        $api->delete('/data/{table:[a-z][a-z0-9_]{0,62}}', [DataApiController::class, 'destroyMany']);
        $api->get('/data/{table:[a-z][a-z0-9_]{0,62}}/{id:[A-Za-z0-9\-]{1,36}}', [DataApiController::class, 'show']);
        $api->patch('/data/{table:[a-z][a-z0-9_]{0,62}}/{id:[A-Za-z0-9\-]{1,36}}', [DataApiController::class, 'update']);
        $api->put('/data/{table:[a-z][a-z0-9_]{0,62}}/{id:[A-Za-z0-9\-]{1,36}}', [DataApiController::class, 'update']);
        $api->delete('/data/{table:[a-z][a-z0-9_]{0,62}}/{id:[A-Za-z0-9\-]{1,36}}', [DataApiController::class, 'destroy']);
        $api->post('/email/send', [EmailApiController::class, 'send']);
        $api->get('/email/{id:\d+}', [EmailApiController::class, 'show']);
    });

    // Images stockées hors du web (sans session).
    $router->get('/media/avatars/{file:[a-f0-9]{32}\.(?:webp|png)}', [MediaController::class, 'avatar'], name: 'media.avatar');

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

        // Invitation d'un proche : accessible sans compte.
        $web->get('/invitation/{token:[a-f0-9]{64}}', [InvitationAcceptController::class, 'show'], name: 'invitation');
        $web->post('/invitation/{token:[a-f0-9]{64}}', [InvitationAcceptController::class, 'accept']);

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

                    // Projets (une base MySQL isolée chacun).
                    $console->get('/projects', [ProjectController::class, 'index'], name: 'projects');
                    $console->get('/projects/new', [ProjectController::class, 'create'], name: 'projects.create');
                    $console->post('/projects', [ProjectController::class, 'store']);
                    $console->group('/projects/{ref:[a-z0-9]{20}}', [], static function (Router $p): void {
                        $p->get('/', [ProjectController::class, 'show'], name: 'projects.show');
                        $p->get('/api', [ProjectController::class, 'api'], name: 'projects.api');
                        $p->get('/openapi.json', [ProjectController::class, 'openapi']);
                        $p->post('/database/reveal', [ProjectController::class, 'revealPassword']);
                        $p->get('/keys', [ProjectController::class, 'keys'], name: 'projects.keys');
                        $p->post('/keys', [ProjectController::class, 'storeKey']);
                        $p->post('/keys/{key:\\d+}/regenerate', [ProjectController::class, 'regenerateKey']);
                        $p->post('/keys/{key:\\d+}/revoke', [ProjectController::class, 'revokeKey']);
                        $p->get('/settings', [ProjectController::class, 'settings'], name: 'projects.settings');
                        $p->post('/settings', [ProjectController::class, 'rename']);
                        $p->post('/settings/cors', [ProjectController::class, 'updateCors']);
                        $p->post('/delete', [ProjectController::class, 'destroy']);

                        // E-mails du projet.
                        $p->get('/emails', [ProjectEmailController::class, 'index'], name: 'projects.emails');
                        $p->post('/emails/smtp', [ProjectEmailController::class, 'save']);
                        $p->post('/emails/smtp/delete', [ProjectEmailController::class, 'remove']);
                        $p->post('/emails/test', [ProjectEmailController::class, 'test']);

                        // Éditeur SQL (requêtes exécutées avec l'utilisateur MySQL du projet).
                        $p->get('/sql', [SqlEditorController::class, 'index'], name: 'projects.sql');
                        $p->post('/sql', [SqlEditorController::class, 'run']);

                        // Table Editor.
                        $p->get('/tables', [TableController::class, 'index'], name: 'projects.tables');
                        $p->get('/tables/new', [TableController::class, 'create']);
                        $p->post('/tables', [TableController::class, 'store']);
                        $p->group('/tables/{table:[a-z][a-z0-9_]{0,62}}', [], static function (Router $t): void {
                            $t->get('/', [TableController::class, 'show']);
                            $t->post('/columns', [TableController::class, 'addColumn']);
                            $t->post('/columns/delete', [TableController::class, 'dropColumn']);
                            $t->post('/rename', [TableController::class, 'rename']);
                            $t->post('/delete', [TableController::class, 'destroy']);
                            $t->post('/rules', [TableController::class, 'updateRules']);
                            $t->post('/rows/delete', [TableController::class, 'deleteRows']);
                            $t->post('/truncate', [TableController::class, 'truncate']);
                            $t->get('/rows/new', [TableController::class, 'newRow']);
                            $t->post('/rows', [TableController::class, 'storeRow']);
                            $t->get('/rows/{id:[A-Za-z0-9\\-]{1,36}}', [TableController::class, 'editRow']);
                            $t->post('/rows/{id:[A-Za-z0-9\\-]{1,36}}', [TableController::class, 'updateRow']);
                            $t->post('/rows/{id:[A-Za-z0-9\\-]{1,36}}/delete', [TableController::class, 'deleteRow']);
                            $t->get('/export.csv', [TableController::class, 'export']);
                            $t->post('/import', [TableController::class, 'import']);
                        });
                    });

                    // Administration de la plateforme : superadmin uniquement.
                    $console->group('/admin', [RequireSuperadmin::class], static function (Router $admin): void {
                        $admin->get('/', [AdminController::class, 'dashboard'], name: 'admin');
                        $admin->get('/metrics', [AdminController::class, 'metrics'], name: 'admin.metrics');
                        $admin->get('/users', [UserController::class, 'index'], name: 'admin.users');
                        $admin->post('/users/{id:\\d+}/status', [UserController::class, 'updateStatus']);
                        $admin->post('/users/{id:\\d+}/plan', [UserController::class, 'updatePlan']);
                        $admin->get('/projects', [ProjectAdminController::class, 'index'], name: 'admin.projects');
                        $admin->post('/projects/{ref:[a-z0-9]{20}}/suspend', [ProjectAdminController::class, 'toggleSuspend']);
                        $admin->get('/invitations', [InvitationController::class, 'index'], name: 'admin.invitations');
                        $admin->post('/invitations', [InvitationController::class, 'store']);
                        $admin->post('/invitations/{id:\\d+}/resend', [InvitationController::class, 'resend']);
                        $admin->post('/invitations/{id:\\d+}/revoke', [InvitationController::class, 'revoke']);
                        $admin->get('/logins', [LoginLogController::class, 'index'], name: 'admin.logins');
                        $admin->get('/logins/feed', [LoginLogController::class, 'feed'], name: 'admin.logins.feed');
                        $admin->get('/community', [AdminController::class, 'showCommunity'], name: 'admin.community');
                        $admin->get('/emails', [EmailSettingsController::class, 'index'], name: 'admin.emails');
                        $admin->post('/emails/smtp', [EmailSettingsController::class, 'save']);
                        $admin->post('/emails/test', [EmailSettingsController::class, 'test']);
                        $admin->get('/emails/templates/{key:[a-z\\-]{3,40}}', [EmailSettingsController::class, 'editTemplate']);
                        $admin->post('/emails/templates/{key:[a-z\\-]{3,40}}', [EmailSettingsController::class, 'saveTemplate']);
                        $admin->post('/emails/templates/{key:[a-z\\-]{3,40}}/reset', [EmailSettingsController::class, 'resetTemplate']);
                        $admin->get('/emails/templates/{key:[a-z\\-]{3,40}}/preview', [EmailSettingsController::class, 'preview']);
                        $admin->post('/community', [AdminController::class, 'updateCommunity']);
                        $admin->get('/testimonials', [TestimonialController::class, 'index'], name: 'admin.testimonials');
                        $admin->get('/testimonials/new', [TestimonialController::class, 'create'], name: 'admin.testimonials.create');
                        $admin->post('/testimonials', [TestimonialController::class, 'store']);
                        $admin->get('/testimonials/{id:\d+}/edit', [TestimonialController::class, 'edit'], name: 'admin.testimonials.edit');
                        $admin->post('/testimonials/{id:\d+}', [TestimonialController::class, 'update']);
                        $admin->post('/testimonials/{id:\d+}/publish', [TestimonialController::class, 'togglePublish']);
                        $admin->post('/testimonials/{id:\d+}/delete', [TestimonialController::class, 'delete']);
                    });
                });
            });
        });

        // Boîte de réception de développement (refusée hors APP_ENV=local + MAIL_DRIVER=log).
        $web->get('/_dev/mail', [DevMailController::class, 'index'], name: 'dev.mail');
        $web->get('/_dev/mail/{id:[0-9]{8}-[0-9]{6}-[0-9]{6}[a-f0-9]{4}}', [DevMailController::class, 'show']);
    });
};
