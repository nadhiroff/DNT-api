<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Database\Database;
use App\Core\Database\DatabaseManager;
use App\Core\Http\Pipeline;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Responder;
use App\Core\Http\Router;
use App\Core\Http\UrlGenerator;
use App\Core\Log\Logger;
use App\Core\Security\CspNonce;
use App\Core\Security\Csrf;
use App\Core\Security\PasswordHasher;
use App\Core\Security\PasswordPolicy;
use App\Core\Session\DatabaseSessionHandler;
use App\Core\Session\Session;
use App\Core\View\View;
use App\Middleware\Cors;
use App\Middleware\HandleErrors;
use App\Middleware\SecurityHeaders;
use App\Repositories\HealthRepository;
use App\Controllers\Web\DevMailController;
use App\Services\AvatarService;
use App\Services\Crypto;
use App\Services\ServerMetrics;
use App\Services\Mail\MailTransport;
use App\Services\HealthService;
use Closure;
use Throwable;

/**
 * Noyau : charge l'environnement, la configuration, enregistre les services et traite les requêtes.
 */
final class Application
{
    /** Middlewares globaux, appliqués à toutes les requêtes (y compris 404). */
    private const array GLOBAL_MIDDLEWARE = [SecurityHeaders::class, Cors::class, HandleErrors::class];

    private function __construct(
        private readonly string $basePath,
        private readonly Container $container,
    ) {
    }

    public static function create(string $basePath, bool $loadEnv = true): self
    {
        // Les fichiers de config/ refusent d'être exécutés hors de l'application.
        if (!defined('NOVABASE_ROOT')) {
            define('NOVABASE_ROOT', $basePath);
        }
        if ($loadEnv) {
            Env::load($basePath . '/config/env.php');
        }
        $config = Config::load($basePath . '/config');
        date_default_timezone_set($config->string('app.timezone', 'UTC'));
        mb_internal_encoding('UTF-8');

        $container = new Container();
        $app = new self($basePath, $container);
        $container->instance(self::class, $app);
        $container->instance(Config::class, $config);
        $app->registerServices($config);

        return $app;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function config(): Config
    {
        return $this->container->get(Config::class);
    }

    /**
     * Point d'entrée HTTP (public/index.php).
     */
    public function run(): void
    {
        $this->container->get(ErrorHandler::class)->register();
        $request = Request::fromGlobals();
        $response = $this->handle($request);
        $response->send($request->isMethod('HEAD'));
    }

    public function handle(Request $request): Response
    {
        $request->withTrustedProxies($this->config()->array('app.trusted_proxies'));
        $this->container->instance(Request::class, $request);
        $this->container->get(UrlGenerator::class)->setRequest($request);
        $this->container->get(Logger::class)->setRequestId($request->requestId());

        try {
            $middleware = array_map(fn (string $class): Http\MiddlewareInterface => $this->middleware($class), self::GLOBAL_MIDDLEWARE);

            return (new Pipeline($middleware, Closure::fromCallable([$this, 'dispatch'])))->handle($request);
        } catch (Throwable $e) {
            // Filet de sécurité si un middleware global échoue lui-même.
            $response = $this->container->get(ErrorHandler::class)->render($request, $e);
            SecurityHeaders::apply($response, $request, $this->container->get(CspNonce::class)->value(), 0);

            return $response;
        }
    }

    /**
     * Résout la route puis exécute ses middlewares et son contrôleur.
     */
    private function dispatch(Request $request): Response
    {
        $match = $this->container->get(Router::class)->match($request->method(), $request->path());
        $route = $match['route'];
        $request->setAttribute('route_params', $match['params']);
        $request->setAttribute('route_name', $route->name);

        $middleware = array_map(fn (string $class): Http\MiddlewareInterface => $this->middleware($class), $route->middleware);
        [$class, $method] = $route->handler;

        return (new Pipeline($middleware, function (Request $request) use ($class, $method): Response {
            $controller = $this->container->resolve($class);
            if (!is_object($controller) || !method_exists($controller, $method)) {
                throw new \LogicException('Action de contrôleur introuvable : ' . $class . '::' . $method);
            }
            $response = $controller->{$method}($request);
            if (!$response instanceof Response) {
                throw new \LogicException($class . '::' . $method . ' doit retourner une Response.');
            }

            return $response;
        }))->handle($request);
    }

    private function middleware(string $class): Http\MiddlewareInterface
    {
        $instance = $this->container->resolve($class);
        if (!$instance instanceof Http\MiddlewareInterface) {
            throw new \LogicException($class . ' n\'est pas un middleware.');
        }

        return $instance;
    }

    private function registerServices(Config $config): void
    {
        $c = $this->container;
        $base = $this->basePath;

        $c->singleton(Logger::class, static fn (): Logger => new Logger(
            $base . '/storage/logs',
            $config->string('app.log_level', 'info'),
            PHP_SAPI === 'cli' ? 'cli' : 'app',
        ));
        $c->singleton(DatabaseManager::class, static fn (): DatabaseManager => new DatabaseManager($config));
        $c->singleton(Database::class, static fn (Container $c): Database => $c->get(DatabaseManager::class)->platform());
        $c->singleton(Crypto::class, static fn (): Crypto => new Crypto($config->string('app.key')));
        $c->singleton(PasswordHasher::class, static fn (): PasswordHasher => new PasswordHasher(
            $config->int('security.argon.memory_cost', 65536),
            $config->int('security.argon.time_cost', 4),
            $config->int('security.argon.threads', 1),
        ));
        $c->singleton(PasswordPolicy::class, static fn (): PasswordPolicy => new PasswordPolicy($base . '/resources/security/common-passwords.txt'));
        $c->singleton(UrlGenerator::class, static fn (): UrlGenerator => new UrlGenerator($config, $base . '/public'));
        $c->singleton(Session::class, static function (Container $c) use ($config): Session {
            $handler = $config->string('security.session.driver', 'database') === 'database'
                ? new DatabaseSessionHandler($c->get(Database::class), $config->int('security.session.idle_lifetime', 7200))
                : null;
            $secure = $config->get('security.session.secure');

            return new Session(
                $handler,
                $config->string('security.session.cookie', 'nb_session'),
                $config->int('security.session.idle_lifetime', 7200),
                is_bool($secure) ? $secure : null,
            );
        });
        $c->singleton(View::class, static function (Container $c) use ($base, $config): View {
            $view = new View($base . '/views', $base . '/public', $c->get(CspNonce::class), $c->get(UrlGenerator::class), $c->get(Csrf::class));
            $view->share('appName', $config->string('app.name', 'NovaBase'));
            $view->share('appUrl', $config->string('app.url'));

            return $view;
        });
        $c->singleton(ErrorHandler::class, static fn (Container $c): ErrorHandler => new ErrorHandler(
            $c->get(Logger::class),
            $config->bool('app.debug'),
            $c->get(View::class),
            $c->get(Session::class),
            $c->get(UrlGenerator::class),
            static function (string $path) use ($c): bool {
                try {
                    $c->get(Router::class)->match('GET', $path);

                    return true;
                } catch (Http\HttpException) {
                    return false;
                }
            },
        ));
        $c->singleton(HealthService::class, static fn (Container $c): HealthService => new HealthService(
            $c->get(HealthRepository::class),
            $base . '/storage',
        ));
        $c->singleton(MailTransport::class, static fn (): MailTransport => new MailTransport($config, $base . '/storage/mail'));
        $c->singleton(DevMailController::class, static fn (Container $c): DevMailController => new DevMailController(
            $c->get(Responder::class),
            $config,
            $base . '/storage/mail',
        ));
        $c->singleton(AvatarService::class, static fn (): AvatarService => new AvatarService($base . '/storage/uploads/avatars'));
        $c->singleton(ServerMetrics::class, static fn (): ServerMetrics => new ServerMetrics($base, $base . '/storage/cache/server-metrics.json'));
        $c->instance(Container::class, $c);
        $c->singleton(Router::class, static function () use ($base): Router {
            $router = new Router();
            /** @var callable(Router): void $routes */
            $routes = require $base . '/config/routes.php';
            $routes($router);

            return $router;
        });
    }
}
