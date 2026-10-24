<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Database\Database;
use App\Core\Security\Totp;
use App\Core\Session\Session;
use App\Repositories\RecoveryCodeRepository;
use App\Repositories\UserRepository;
use App\Services\Crypto;
use App\Services\RecoveryCodeService;

/**
 * Double authentification TOTP : enrôlement, vérification (code ou code de secours), désactivation.
 *
 * @phpstan-import-type UserRow from UserRepository
 */
final class TwoFactorService
{
    private const string SETUP_KEY = '_2fa_setup_secret';

    public function __construct(
        private readonly Totp $totp,
        private readonly Crypto $crypto,
        private readonly UserRepository $users,
        private readonly RecoveryCodeRepository $recoveryCodes,
        private readonly RecoveryCodeService $recoveryCodeService,
        private readonly Database $db,
    ) {
    }

    /**
     * Secret d'enrôlement en cours (créé au besoin), conservé chiffré en session.
     */
    public function pendingSecret(Session $session): string
    {
        $stored = $session->get(self::SETUP_KEY);
        if (is_string($stored)) {
            return $this->crypto->decrypt($stored, 'totp_setup');
        }
        $secret = $this->totp->newSecret();
        $session->set(self::SETUP_KEY, $this->crypto->encrypt($secret, 'totp_setup'));

        return $secret;
    }

    public function provisioningUri(string $secret, string $email, string $issuer): string
    {
        return $this->totp->provisioningUri($secret, $email, $issuer);
    }

    /**
     * Valide le premier code, active la 2FA et retourne les codes de secours (affichés une fois).
     *
     * @return list<string>|null null si le code est invalide
     */
    public function confirmSetup(int $userId, Session $session, string $code): ?array
    {
        $secret = $this->pendingSecret($session);
        $step = $this->totp->verify($secret, $code);
        if ($step === null) {
            return null;
        }
        $codes = $this->recoveryCodeService->generate();
        $this->db->transaction(function () use ($userId, $secret, $step, $codes): void {
            $this->users->enableTotp($userId, $this->crypto->encrypt($secret, 'totp_secret'), $step);
            $this->recoveryCodes->replace($userId, array_map($this->recoveryCodeService->hash(...), $codes));
        });
        $session->remove(self::SETUP_KEY);

        return $codes;
    }

    /**
     * Vérifie un code TOTP (anti-rejeu) ou un code de secours (consommé).
     *
     * @param UserRow $user
     */
    public function verify(array $user, string $code): bool
    {
        if ($user['totp_secret_enc'] === null) {
            return false;
        }
        $code = trim($code);
        if (preg_match('/^\d{3}\s?\d{3}$/', $code) === 1) {
            $secret = $this->crypto->decrypt($user['totp_secret_enc'], 'totp_secret');
            $step = $this->totp->verify($secret, $code, $user['totp_last_step']);

            // La mise à jour conditionnelle garantit qu'un même code n'est accepté qu'une fois, même en concurrence.
            return $step !== null && $this->users->setTotpLastStep($user['id'], $step);
        }
        if (strlen(RecoveryCodeService::normalize($code)) === 10) {
            return $this->recoveryCodes->consume($user['id'], $this->recoveryCodeService->hash($code));
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(int $userId): array
    {
        $codes = $this->recoveryCodeService->generate();
        $this->recoveryCodes->replace($userId, array_map($this->recoveryCodeService->hash(...), $codes));

        return $codes;
    }

    public function remainingRecoveryCodes(int $userId): int
    {
        return $this->recoveryCodes->remaining($userId);
    }

    public function disable(int $userId): void
    {
        $this->db->transaction(function () use ($userId): void {
            $this->users->disableTotp($userId);
            $this->recoveryCodes->replace($userId, []);
        });
    }

    /**
     * La 2FA est obligatoire pour les rôles d'administration.
     */
    public static function isRequiredFor(string $role): bool
    {
        return in_array($role, ['superadmin', 'admin'], true);
    }
}
