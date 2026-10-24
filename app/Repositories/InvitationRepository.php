<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Invitations des proches : le compte associé reste « pending » jusqu'à l'acceptation.
 *
 * @phpstan-type InvitationRow array{id: int, email: string, user_id: int|null, invited_by: int|null, inviter_name: string|null, message: string|null, status: string, expires_at: string, accepted_at: string|null, created_at: string}
 */
final class InvitationRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function create(string $email, int $userId, int $invitedBy, string $tokenHash, ?string $message, int $ttlSeconds): int
    {
        return $this->db->insert(
            'INSERT INTO invitations (email, user_id, invited_by, token_hash, message, expires_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? SECOND)',
            [mb_strtolower($email), $userId, $invitedBy, $tokenHash, $message, $ttlSeconds],
        );
    }

    /** @return list<InvitationRow> */
    public function latest(int $limit = 100): array
    {
        // Les invitations dépassées sont marquées expirées à la lecture.
        $this->db->execute("UPDATE invitations SET status = 'expired' WHERE status = 'pending' AND expires_at < UTC_TIMESTAMP()");

        return array_map(self::hydrate(...), $this->db->select(
            'SELECT i.id, i.email, i.user_id, i.invited_by, u.name AS inviter_name, i.message, i.status, i.expires_at, i.accepted_at, i.created_at
             FROM invitations i LEFT JOIN users u ON u.id = i.invited_by ORDER BY i.id DESC LIMIT ?',
            [$limit],
        ));
    }

    /** @return InvitationRow|null */
    public function find(int $id): ?array
    {
        $row = $this->db->selectOne(
            'SELECT i.id, i.email, i.user_id, i.invited_by, u.name AS inviter_name, i.message, i.status, i.expires_at, i.accepted_at, i.created_at
             FROM invitations i LEFT JOIN users u ON u.id = i.invited_by WHERE i.id = ?',
            [$id],
        );

        return $row !== null ? self::hydrate($row) : null;
    }

    /** @return InvitationRow|null */
    public function findPendingByHash(string $hash): ?array
    {
        $row = $this->db->selectOne(
            "SELECT i.id, i.email, i.user_id, i.invited_by, u.name AS inviter_name, i.message, i.status, i.expires_at, i.accepted_at, i.created_at
             FROM invitations i LEFT JOIN users u ON u.id = i.invited_by
             WHERE i.token_hash = ? AND i.status = 'pending' AND i.expires_at > UTC_TIMESTAMP()",
            [$hash],
        );

        return $row !== null ? self::hydrate($row) : null;
    }

    public function hasPending(string $email): bool
    {
        return $this->db->scalar("SELECT 1 FROM invitations WHERE email = ? AND status = 'pending' AND expires_at > UTC_TIMESTAMP()", [mb_strtolower($email)]) !== null;
    }

    public function renewToken(int $id, string $hash, int $ttlSeconds): void
    {
        $this->db->execute(
            "UPDATE invitations SET token_hash = ?, status = 'pending', expires_at = UTC_TIMESTAMP() + INTERVAL ? SECOND WHERE id = ?",
            [$hash, $ttlSeconds, $id],
        );
    }

    /**
     * Passe l'invitation à « accepted » si elle est toujours en attente (atomique).
     */
    public function markAccepted(int $id): bool
    {
        return $this->db->execute("UPDATE invitations SET status = 'accepted', accepted_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'pending'", [$id]) === 1;
    }

    public function markRevoked(int $id): void
    {
        $this->db->execute("UPDATE invitations SET status = 'revoked' WHERE id = ? AND status IN ('pending', 'expired')", [$id]);
    }

    public function countPending(): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM invitations WHERE status = 'pending' AND expires_at > UTC_TIMESTAMP()");
    }

    /**
     * @param array<string, mixed> $r
     *
     * @return InvitationRow
     */
    private static function hydrate(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'email' => (string) $r['email'],
            'user_id' => $r['user_id'] !== null ? (int) $r['user_id'] : null,
            'invited_by' => $r['invited_by'] !== null ? (int) $r['invited_by'] : null,
            'inviter_name' => $r['inviter_name'] !== null ? (string) $r['inviter_name'] : null,
            'message' => $r['message'] !== null ? (string) $r['message'] : null,
            'status' => (string) $r['status'],
            'expires_at' => (string) $r['expires_at'],
            'accepted_at' => $r['accepted_at'] !== null ? (string) $r['accepted_at'] : null,
            'created_at' => (string) $r['created_at'],
        ];
    }
}
