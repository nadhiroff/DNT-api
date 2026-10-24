<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

final class PlanRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Plans affichés sur la page Tarifs.
     *
     * @return list<array{name: string, slug: string, description: string, price: string, currency: string, interval: string, features: list<string>, is_default: bool, is_available: bool, is_featured: bool}>
     */
    public function publicPlans(): array
    {
        $plans = [];
        foreach ($this->db->select('SELECT name, slug, description, price, currency, billing_interval, features, is_default, is_available, is_featured FROM plans WHERE is_public = 1 ORDER BY sort, id') as $row) {
            $features = is_string($row['features']) ? json_decode($row['features'], true) : null;
            $plans[] = [
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'description' => (string) ($row['description'] ?? ''),
                'price' => (string) $row['price'],
                'currency' => (string) $row['currency'],
                'interval' => (string) $row['billing_interval'],
                'features' => is_array($features) ? array_values(array_filter($features, 'is_string')) : [],
                'is_default' => (bool) $row['is_default'],
                'is_available' => (bool) $row['is_available'],
                'is_featured' => (bool) $row['is_featured'],
            ];
        }

        return $plans;
    }

    /**
     * Limites d'un plan (clé absente ou valeur null = illimité).
     *
     * @return array<string, int|null>
     */
    public function limits(int $planId): array
    {
        $json = $this->db->scalar('SELECT limits FROM plans WHERE id = ?', [$planId]);
        $limits = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($limits)) {
            return [];
        }
        $result = [];
        foreach ($limits as $key => $value) {
            if (is_string($key)) {
                $result[$key] = is_int($value) ? $value : null;
            }
        }

        return $result;
    }

    /** @return list<array{id: int, name: string}> */
    public function options(): array
    {
        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name']], $this->db->select('SELECT id, name FROM plans ORDER BY sort, id'));
    }

    public function defaultPlanId(): ?int
    {
        $id = $this->db->scalar('SELECT id FROM plans WHERE is_default = 1 ORDER BY sort, id LIMIT 1');

        return $id !== null ? (int) $id : null;
    }
}
