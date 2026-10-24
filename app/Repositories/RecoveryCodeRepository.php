<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Codes de secours 2FA (seuls leurs HMAC sont stockés).
 */
final class RecoveryCodeRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Remplace tous les codes d'un utilisateur.
     *
     * @param list<string> $hashes
     */
    public function replace(int $userId, array $hashes): void
    {
        $this->db->transaction(function (Database $db) use ($userId, $hashes): void {
            $db->execute('DELETE FROM user_recovery_codes WHERE user_id = ?', [$userId]);
            foreach ($hashes as $hash) {
                $db->execute('INSERT INTO user_recovery_codes (user_id, code_hash) VALUES (?, ?)', [$userId, $hash]);
            }
        });
    }

    /**
     * Consomme un code (usage unique). Retourne vrai si un code valide non utilisé a été trouvé.
     */
    public function consume(int $userId, string $hash): bool
    {
        return $this->db->execute(
            'UPDATE user_recovery_codes SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND code_hash = ? AND used_at IS NULL',
            [$userId, $hash],
        ) === 1;
    }

    public function remaining(int $userId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM user_recovery_codes WHERE user_id = ? AND used_at IS NULL', [$userId]);
    }
}
