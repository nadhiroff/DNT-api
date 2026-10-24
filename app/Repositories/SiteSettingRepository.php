<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Réglages éditables du site public (clé → valeur).
 */
final class SiteSettingRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, string>
     */
    public function many(array $keys): array
    {
        $values = array_fill_keys($keys, '');
        if ($keys === []) {
            return $values;
        }
        foreach ($this->db->select('SELECT setting_key, value FROM site_settings WHERE setting_key IN (' . Database::placeholders($keys) . ')', $keys) as $row) {
            $values[(string) $row['setting_key']] = (string) ($row['value'] ?? '');
        }

        return $values;
    }

    public function set(string $key, ?string $value): void
    {
        $this->db->execute(
            'INSERT INTO site_settings (setting_key, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$key, $value],
        );
    }
}
