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

    public function defaultPlanId(): ?int
    {
        $id = $this->db->scalar('SELECT id FROM plans WHERE is_default = 1 ORDER BY sort, id LIMIT 1');

        return $id !== null ? (int) $id : null;
    }
}
