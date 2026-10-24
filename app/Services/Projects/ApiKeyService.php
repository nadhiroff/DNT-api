<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Repositories\ApiKeyRepository;
use App\Services\Crypto;

/**
 * Clés API : 256 bits aléatoires, affichées une seule fois, stockées en SHA-256.
 * Format : pk_live_<43 caractères> (publique) / sk_live_<43 caractères> (secrète).
 *
 * @phpstan-import-type ApiKeyRow from ApiKeyRepository
 */
final class ApiKeyService
{
    public const array PREFIXES = ['public' => 'pk_live_', 'secret' => 'sk_live_'];

    /** Droits attribués automatiquement selon le type de clé. */
    public const array PERMISSIONS = [
        'public' => ['read', 'write'],
        'secret' => ['read', 'write', 'storage', 'email', 'bypass_rules'],
    ];

    public function __construct(
        private readonly ApiKeyRepository $keys,
        private readonly Crypto $crypto,
    ) {
    }

    /**
     * @return array{id: int, key: string}
     */
    public function generate(int $projectId, string $type, string $name, ?string $expiresAt = null): array
    {
        if (!isset(self::PREFIXES[$type])) {
            throw new \InvalidArgumentException('Type de clé inconnu.');
        }
        $key = self::PREFIXES[$type] . rtrim(strtr(base64_encode(random_bytes(32)), '+/', 'AZ'), '=');
        $id = $this->keys->create(
            $projectId,
            $name,
            $type,
            substr($key, 0, 12),
            hash('sha256', $key),
            $this->crypto->encrypt($key, 'api_key'),
            self::PERMISSIONS[$type],
            $expiresAt,
        );

        return ['id' => $id, 'key' => $key];
    }

    /**
     * Valeur complète d'une clé (copie depuis la console). null pour une clé créée avant le chiffrement.
     *
     * @param ApiKeyRow $key
     */
    public function plain(array $key): ?string
    {
        return $key['key_enc'] !== null ? $this->crypto->decrypt($key['key_enc'], 'api_key') : null;
    }

    /**
     * Clés actives par défaut (publique et secrète) pour le fichier .env de l'application.
     *
     * @return array{public: string|null, secret: string|null}
     */
    public function integrationKeys(int $projectId): array
    {
        $public = $this->keys->firstActive($projectId, 'public');
        $secret = $this->keys->firstActive($projectId, 'secret');

        return ['public' => $public !== null ? $this->plain($public) : null, 'secret' => $secret !== null ? $this->plain($secret) : null];
    }

    /**
     * Régénération = révocation immédiate de l'ancienne + nouvelle clé de même type.
     *
     * @return array{id: int, key: string}|null
     */
    public function regenerate(int $projectId, int $keyId): ?array
    {
        $old = $this->keys->find($projectId, $keyId);
        if ($old === null || $old['revoked_at'] !== null) {
            return null;
        }
        $this->keys->revoke($projectId, $keyId);

        return $this->generate($projectId, isset(self::PREFIXES[$old['type']]) ? $old['type'] : 'secret', $old['name'], $old['expires_at']);
    }

    public function revoke(int $projectId, int $keyId): bool
    {
        return $this->keys->revoke($projectId, $keyId);
    }

    /**
     * @return ApiKeyRow|null clé utilisable pour ce projet, sinon null (401)
     */
    public function authenticate(string $plainKey, int $projectId): ?array
    {
        if (preg_match('/^(pk|sk)_live_[A-Za-z0-9\-_]{40,64}$/', $plainKey) !== 1) {
            return null;
        }
        $key = $this->keys->findUsable(hash('sha256', $plainKey), $projectId);
        if ($key !== null) {
            $this->keys->touch($key['id']);
        }

        return $key;
    }

    /** @return list<ApiKeyRow> */
    public function forProject(int $projectId): array
    {
        return $this->keys->forProject($projectId);
    }
}
