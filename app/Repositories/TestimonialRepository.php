<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Témoignages de la page d'accueil.
 *
 * @phpstan-type TestimonialRow array{id: int, author_name: string, handle: string, platform: string, profile_url: string|null, avatar_file: string|null, content: string, is_published: bool, sort: int}
 */
final class TestimonialRepository
{
    private const string COLUMNS = 'id, author_name, handle, platform, profile_url, avatar_file, content, is_published, sort';

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<TestimonialRow> */
    public function published(int $limit = 40): array
    {
        return array_map(self::hydrate(...), $this->db->select(
            'SELECT ' . self::COLUMNS . ' FROM testimonials WHERE is_published = 1 ORDER BY sort, id LIMIT ?',
            [$limit],
        ));
    }

    /** @return list<TestimonialRow> */
    public function all(int $limit = 200): array
    {
        return array_map(self::hydrate(...), $this->db->select(
            'SELECT ' . self::COLUMNS . ' FROM testimonials ORDER BY sort, id LIMIT ?',
            [$limit],
        ));
    }

    /** @return TestimonialRow|null */
    public function find(int $id): ?array
    {
        $row = $this->db->selectOne('SELECT ' . self::COLUMNS . ' FROM testimonials WHERE id = ?', [$id]);

        return $row !== null ? self::hydrate($row) : null;
    }

    public function countPublished(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM testimonials WHERE is_published = 1');
    }

    /**
     * @param array{author_name: string, handle: string, platform: string, profile_url: string|null, avatar_file: string|null, content: string, is_published: bool, sort: int} $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO testimonials (author_name, handle, platform, profile_url, avatar_file, content, is_published, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['author_name'], $data['handle'], $data['platform'], $data['profile_url'], $data['avatar_file'], $data['content'], $data['is_published'], $data['sort']],
        );
    }

    /**
     * @param array{author_name: string, handle: string, platform: string, profile_url: string|null, avatar_file: string|null, content: string, is_published: bool, sort: int} $data
     */
    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE testimonials SET author_name = ?, handle = ?, platform = ?, profile_url = ?, avatar_file = ?, content = ?, is_published = ?, sort = ? WHERE id = ?',
            [$data['author_name'], $data['handle'], $data['platform'], $data['profile_url'], $data['avatar_file'], $data['content'], $data['is_published'], $data['sort'], $id],
        );
    }

    public function setPublished(int $id, bool $published): void
    {
        $this->db->execute('UPDATE testimonials SET is_published = ? WHERE id = ?', [$published, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM testimonials WHERE id = ?', [$id]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return TestimonialRow
     */
    private static function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'author_name' => (string) $row['author_name'],
            'handle' => (string) $row['handle'],
            'platform' => (string) $row['platform'],
            'profile_url' => $row['profile_url'] !== null ? (string) $row['profile_url'] : null,
            'avatar_file' => $row['avatar_file'] !== null ? (string) $row['avatar_file'] : null,
            'content' => (string) $row['content'],
            'is_published' => (bool) $row['is_published'],
            'sort' => (int) $row['sort'],
        ];
    }
}
