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
