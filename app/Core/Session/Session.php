<?php

declare(strict_types=1);

namespace App\Core\Session;

use App\Core\Http\Request;
use RuntimeException;
use SessionHandlerInterface;

/**
 * Session web durcie : cookie HttpOnly/Secure/SameSite=Lax, mode strict, expiration
 * d'inactivité, régénération de l'identifiant et messages flash.
 */
final class Session
{
    private const string LAST_ACTIVITY = '_last_activity';

    private const string USER_ID = '_uid';

    private const string FLASH_NEW = '_flash_new';

    private const string FLASH_OLD = '_flash_old';

    /** @var array<string, mixed> */
    private array $data = [];

    private bool $started = false;

    private bool $native = false;

    private bool $expired = false;

    public function __construct(
        private readonly ?SessionHandlerInterface $handler,
        private readonly string $cookieName = 'nb_session',
        private readonly int $idleLifetime = 7200,
        private readonly ?bool $forceSecure = null,
    ) {
    }

    /**
     * Démarre la session native à partir de la requête courante.
     */
    public function start(Request $request): void
    {
        if ($this->started) {
            // Session en mémoire (tests) : chaque requête simulée fait tourner les messages flash,
            // comme le ferait une nouvelle requête HTTP.
            if (!$this->native) {
                $this->boot();
            }

            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Une session native est déjà active.');
        }
        $secure = $this->forceSecure ?? $request->isSecure();
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.lazy_write', '1');
        ini_set('session.gc_maxlifetime', (string) $this->idleLifetime);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '200');
        session_name($this->cookieName);
        session_set_cookie_params([
            'lifetime' => 0,
            // Cookie limité au dossier d'installation (« /DNT-api ») quand l'application n'est pas à la racine.
            'path' => $request->basePath() !== '' ? $request->basePath() : '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_cache_limiter('nocache');
        if ($this->handler !== null) {
            if ($this->handler instanceof DatabaseSessionHandler) {
                $this->handler->setContext(null, $request->ip(), $request->userAgent());
            }
            session_set_save_handler($this->handler, true);
        }
        if (!session_start()) {
            throw new RuntimeException('Impossible de démarrer la session.');
        }
        $this->native = true;
        $this->data = &$_SESSION;
        $this->boot();
        if ($this->handler instanceof DatabaseSessionHandler) {
            $this->handler->setUserId($this->userId());
        }
    }

    /**
     * Session en mémoire (CLI, tests) : même API, sans cookie ni stockage.
     *
     * @param array<string, mixed> $data
     */
    public function startInMemory(array $data = []): void
    {
        $this->data = $data;
        $this->started = true;
        $this->native = false;
        $this->boot();
    }

    private function boot(): void
    {
        $this->started = true;
        $now = time();
        $last = $this->data[self::LAST_ACTIVITY] ?? null;
        if (is_int($last) && $now - $last > $this->idleLifetime) {
            $this->data = [];
            $this->expired = true;
            $this->regenerate();
        }
        $this->data[self::LAST_ACTIVITY] = $now;
        // Rotation des messages flash : ceux de la requête précédente deviennent lisibles.
        $this->data[self::FLASH_OLD] = $this->data[self::FLASH_NEW] ?? [];
        $this->data[self::FLASH_NEW] = [];
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    /** Vrai si la session précédente a expiré pour inactivité pendant ce démarrage. */
    public function wasExpired(): bool
    {
        return $this->expired;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertStarted();

        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->assertStarted();
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->assertStarted();

        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        $this->assertStarted();
        unset($this->data[$key]);
    }

    /**
     * Retire et retourne une valeur (utile pour les données à usage unique).
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);

        return $value;
    }

    public function flash(string $key, mixed $value): void
    {
        $this->assertStarted();
        $new = is_array($this->data[self::FLASH_NEW] ?? null) ? $this->data[self::FLASH_NEW] : [];
        $new[$key] = $value;
        $this->data[self::FLASH_NEW] = $new;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->assertStarted();
        $old = $this->data[self::FLASH_OLD] ?? [];
        $new = $this->data[self::FLASH_NEW] ?? [];

        return (is_array($new) && array_key_exists($key, $new) ? $new[$key] : null)
            ?? (is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default);
    }

    public function userId(): ?int
    {
        $id = $this->data[self::USER_ID] ?? null;

        return is_int($id) ? $id : null;
    }

    /**
     * Associe la session à un utilisateur en régénérant l'identifiant (anti-fixation).
     */
    public function login(int $userId): void
    {
        $this->assertStarted();
        $this->regenerate();
        $this->data[self::USER_ID] = $userId;
        if ($this->handler instanceof DatabaseSessionHandler) {
            $this->handler->setUserId($userId);
        }
    }

    /**
     * Vide la session et attribue un nouvel identifiant.
     */
    public function invalidate(): void
    {
        $this->assertStarted();
        $this->data = [self::LAST_ACTIVITY => time(), self::FLASH_NEW => [], self::FLASH_OLD => []];
        if ($this->handler instanceof DatabaseSessionHandler) {
            $this->handler->setUserId(null);
        }
        $this->regenerate();
    }

    public function regenerate(): void
    {
        if ($this->native && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function id(): string
    {
        return $this->native ? (string) session_id() : '';
    }

    /**
     * Écrit et ferme la session (appelé en fin de requête).
     */
    public function save(): void
    {
        if ($this->native && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    private function assertStarted(): void
    {
        if (!$this->started) {
            throw new RuntimeException('La session n\'est pas démarrée (middleware StartSession manquant ?).');
        }
    }
}
