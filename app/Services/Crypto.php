<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Chiffrement authentifié des secrets stockés en base (mots de passe SMTP/MySQL, clés JWT, TOTP)
 * avec sodium_crypto_secretbox (XSalsa20-Poly1305). La clé maître ne vit que dans config/env.php (APP_KEY), jamais en base.
 *
 * Format : « v1: » + base64(nonce || ciphertext). Le préfixe de version permet une future rotation.
 */
final class Crypto
{
    private const string PREFIX = 'v1:';

    private string $key;

    public function __construct(string $masterKey)
    {
        $key = self::decodeKey($masterKey);
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('APP_KEY invalide : exécutez « php install.php » ou « php bin/console key:generate ».');
        }
        $this->key = $key;
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(sodium_crypto_secretbox_keygen());
    }

    public function encrypt(string $plaintext, string $context = ''): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $this->contextKey($context));

        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    /**
     * @param string $context lie le chiffré à son usage (ex. « smtp_password ») : un chiffré déplacé
     *                        vers une autre colonne ne se déchiffre pas.
     */
    public function decrypt(string $payload, string $context = ''): string
    {
        if (!str_starts_with($payload, self::PREFIX)) {
            throw new RuntimeException('Format de secret chiffré inconnu.');
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new RuntimeException('Secret chiffré corrompu.');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->contextKey($context));
        if ($plain === false) {
            throw new RuntimeException('Échec du déchiffrement : clé maître ou contexte incorrect.');
        }

        return $plain;
    }

    /**
     * HMAC-SHA256 avec une sous-clé dérivée de la clé maître (jetons signés, URL signées…).
     */
    public function hmac(string $data, string $context = 'hmac'): string
    {
        return hash_hmac('sha256', $data, $this->contextKey('hmac:' . $context));
    }

    private function contextKey(string $context): string
    {
        if ($context === '') {
            return $this->key;
        }

        return sodium_crypto_generichash('novabase:' . $context, $this->key, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private static function decodeKey(string $masterKey): string
    {
        if (str_starts_with($masterKey, 'base64:')) {
            $decoded = base64_decode(substr($masterKey, 7), true);

            return $decoded === false ? '' : $decoded;
        }

        return '';
    }
}
