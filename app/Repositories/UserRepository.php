<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Accès à la table users.
 *
 * @phpstan-type UserRow array{id: int, email: string, password_hash: string, name: string, role: string, plan_id: int|null, status: string, email_verified_at: string|null, totp_secret_enc: string|null, totp_enabled_at: string|null, totp_last_step: int|null, created_at: string}
 */
final class UserRepository
{
    private const string COLUMNS = 'id, email, password_hash, name, role, plan_id, status, email_verified_at, totp_secret_enc, totp_enabled_at, totp_last_step, created_at';

    public function __construct(private readonly Database $db)
    {
    }

    /** @return UserRow|null */
    public function findById(int $id): ?array
    {
        return $this->hydrate($this->db->selectOne('SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?', [$id]));
    }

    /** @return UserRow|null */
    public function findByEmail(string $email): ?array
    {
        return $this->hydrate($this->db->selectOne('SELECT ' . self::COLUMNS . ' FROM users WHERE email = ?', [mb_strtolower($email)]));
    }

    public function emailExists(string $email): bool
    {
        return $this->db->scalar('SELECT 1 FROM users WHERE email = ?', [mb_strtolower($email)]) !== null;
    }

    public function countByRole(string $role): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM users WHERE role = ?', [$role]);
    }

    /**
     * @param array{email: string, password_hash: string, name: string, role: string, plan_id: int|null, status: string, email_verified: bool, totp_secret_enc?: string|null} $data
     */
    public function create(array $data): int
    {
        $totp = $data['totp_secret_enc'] ?? null;
        $now = gmdate('Y-m-d H:i:s');

        return $this->db->insert(
            'INSERT INTO users (email, password_hash, name, role, plan_id, status, email_verified_at, totp_secret_enc, totp_enabled_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                mb_strtolower($data['email']),
                $data['password_hash'],
                $data['name'],
                $data['role'],
                $data['plan_id'],
                $data['status'],
                $data['email_verified'] ? $now : null,
                $totp,
                $totp !== null ? $now : null,
            ],
        );
    }

    /**
     * Compte en attente pour une invitation : mot de passe inutilisable tant que l'invitation n'est pas acceptée.
     */
    public function createPending(string $email, ?int $planId): int
    {
        return $this->db->insert(
            "INSERT INTO users (email, password_hash, name, role, plan_id, status) VALUES (?, ?, '', 'user', ?, 'pending')",
            [mb_strtolower($email), '!invitation:' . bin2hex(random_bytes(16)), $planId],
        );
    }

    /**
     * Active un compte invité : nom, mot de passe, adresse vérifiée (le lien prouve l'accès à la boîte).
     */
    public function activateInvited(int $userId, string $name, string $passwordHash): void
    {
        $this->db->execute(
            "UPDATE users SET name = ?, password_hash = ?, status = 'active', email_verified_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'pending'",
            [$name, $passwordHash, $userId],
        );
    }

    public function setStatus(int $userId, string $status): void
    {
        $this->db->execute('UPDATE users SET status = ? WHERE id = ?', [$status, $userId]);
    }

    public function setPlan(int $userId, ?int $planId): void
    {
        $this->db->execute('UPDATE users SET plan_id = ? WHERE id = ?', [$planId, $userId]);
    }

    /**
     * Liste paginée pour le panneau superadmin.
     *
     * @return list<array{id: int, email: string, name: string, role: string, status: string, plan_name: string|null, plan_id: int|null, projects: int, email_verified: bool, last_login_at: string|null, created_at: string}>
     */
    public function listForAdmin(string $search, string $status, int $limit, int $offset): array
    {
        $rows = $this->db->select(
            "SELECT u.id, u.email, u.name, u.role, u.status, u.plan_id, pl.name AS plan_name, u.email_verified_at, u.last_login_at, u.created_at,
                    (SELECT COUNT(*) FROM projects p WHERE p.owner_id = u.id AND p.status <> 'failed') AS projects
             FROM users u LEFT JOIN plans pl ON pl.id = u.plan_id
             WHERE (? = '' OR u.email LIKE ? OR u.name LIKE ?) AND (? = '' OR u.status = ?)
             ORDER BY u.created_at DESC LIMIT ? OFFSET ?",
            [$search, '%' . $search . '%', '%' . $search . '%', $status, $status, $limit, $offset],
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'email' => (string) $r['email'],
            'name' => (string) $r['name'],
            'role' => (string) $r['role'],
            'status' => (string) $r['status'],
            'plan_name' => $r['plan_name'] !== null ? (string) $r['plan_name'] : null,
            'plan_id' => $r['plan_id'] !== null ? (int) $r['plan_id'] : null,
            'projects' => (int) $r['projects'],
            'email_verified' => $r['email_verified_at'] !== null,
            'last_login_at' => $r['last_login_at'] !== null ? (string) $r['last_login_at'] : null,
            'created_at' => (string) $r['created_at'],
        ], $rows);
    }

    public function countForAdmin(string $search, string $status): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM users u WHERE (? = '' OR u.email LIKE ? OR u.name LIKE ?) AND (? = '' OR u.status = ?)",
            [$search, '%' . $search . '%', '%' . $search . '%', $status, $status],
        );
    }

    public function markEmailVerified(int $userId): void
    {
        $this->db->execute('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ? AND email_verified_at IS NULL', [$userId]);
    }

    public function updatePassword(int $userId, string $hash): void
    {
        $this->db->execute('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $userId]);
    }

    public function updateName(int $userId, string $name): void
    {
        $this->db->execute('UPDATE users SET name = ? WHERE id = ?', [$name, $userId]);
    }

    public function recordLogin(int $userId, string $ip): void
    {
        $this->db->execute('UPDATE users SET last_login_at = UTC_TIMESTAMP(), last_login_ip = ? WHERE id = ?', [$ip, $userId]);
    }

    /**
     * Active la 2FA (secret chiffré) et mémorise le pas TOTP de l'enrôlement.
     */
    public function enableTotp(int $userId, string $secretEnc, int $step): void
    {
        $this->db->execute(
            'UPDATE users SET totp_secret_enc = ?, totp_enabled_at = UTC_TIMESTAMP(), totp_last_step = ? WHERE id = ?',
            [$secretEnc, $step, $userId],
        );
    }

    public function disableTotp(int $userId): void
    {
        $this->db->execute('UPDATE users SET totp_secret_enc = NULL, totp_enabled_at = NULL, totp_last_step = NULL WHERE id = ?', [$userId]);
    }

    public function delete(int $userId): void
    {
        $this->db->execute('DELETE FROM users WHERE id = ?', [$userId]);
    }

    /**
     * Mémorise le dernier pas TOTP accepté (anti-rejeu) ; refuse tout retour en arrière.
     */
    public function setTotpLastStep(int $userId, int $step): bool
    {
        return $this->db->execute(
            'UPDATE users SET totp_last_step = ? WHERE id = ? AND (totp_last_step IS NULL OR totp_last_step < ?)',
            [$step, $userId, $step],
        ) === 1;
    }

    /**
     * @param array<string, mixed>|null $row
     *
     * @return UserRow|null
     */
    private function hydrate(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'password_hash' => (string) $row['password_hash'],
            'name' => (string) $row['name'],
            'role' => (string) $row['role'],
            'plan_id' => $row['plan_id'] !== null ? (int) $row['plan_id'] : null,
            'status' => (string) $row['status'],
            'email_verified_at' => $row['email_verified_at'] !== null ? (string) $row['email_verified_at'] : null,
            'totp_secret_enc' => $row['totp_secret_enc'] !== null ? (string) $row['totp_secret_enc'] : null,
            'totp_enabled_at' => $row['totp_enabled_at'] !== null ? (string) $row['totp_enabled_at'] : null,
            'totp_last_step' => $row['totp_last_step'] !== null ? (int) $row['totp_last_step'] : null,
            'created_at' => (string) $row['created_at'],
        ];
    }
}
