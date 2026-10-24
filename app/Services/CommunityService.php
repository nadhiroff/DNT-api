<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SiteSettingRepository;
use App\Repositories\TestimonialRepository;

/**
 * Section « communauté » de la page d'accueil : textes, bouton et témoignages publiés.
 *
 * @phpstan-import-type TestimonialRow from TestimonialRepository
 */
final class CommunityService
{
    public const array SETTINGS = ['community_title', 'community_subtitle', 'community_button_label', 'community_button_url'];

    public const array PLATFORMS = [
        'x' => 'X (Twitter)',
        'linkedin' => 'LinkedIn',
        'github' => 'GitHub',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'other' => 'Autre',
    ];

    public function __construct(
        private readonly TestimonialRepository $testimonials,
        private readonly SiteSettingRepository $settings,
    ) {
    }

    /**
     * Lien vers le compte externe : lien saisi, sinon reconstruit depuis le réseau et l'identifiant.
     */
    public static function profileUrl(string $platform, string $handle, ?string $profileUrl): ?string
    {
        if ($profileUrl !== null && preg_match('#^https?://#i', $profileUrl) === 1) {
            return $profileUrl;
        }
        $name = ltrim($handle, '@');
        if (preg_match('/^[A-Za-z0-9_.\-]{1,60}$/', $name) !== 1) {
            return null;
        }

        return match ($platform) {
            'x' => 'https://x.com/' . rawurlencode($name),
            'github' => 'https://github.com/' . rawurlencode($name),
            'linkedin' => 'https://www.linkedin.com/in/' . rawurlencode($name),
            'instagram' => 'https://www.instagram.com/' . rawurlencode($name),
            'facebook' => 'https://www.facebook.com/' . rawurlencode($name),
            default => null,
        };
    }

    /** @return array<string, string> */
    public function settings(): array
    {
        return $this->settings->many(self::SETTINGS);
    }

    /**
     * Témoignages à afficher. Tant qu'aucun n'est publié, le superadmin voit les brouillons
     * (aperçu signalé) ; le public ne voit rien.
     *
     * @return array{items: list<TestimonialRow>, preview: bool}
     */
    public function forHomepage(bool $isSuperadmin): array
    {
        $published = $this->testimonials->published();
        if ($published === [] && $isSuperadmin) {
            return ['items' => $this->testimonials->all(40), 'preview' => true];
        }

        return ['items' => $published, 'preview' => false];
    }
}
