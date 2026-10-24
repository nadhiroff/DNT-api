<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Services\AvatarService;

/**
 * Sert les images stockées hors du dossier web (photos des témoignages).
 */
final class MediaController
{
    public function __construct(private readonly AvatarService $avatars)
    {
    }

    public function avatar(Request $request): Response
    {
        $path = $this->avatars->path((string) $request->route('file'));
        if ($path === null) {
            throw HttpException::notFound();
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw HttpException::notFound();
        }

        return new Response($content, 200, [
            'Content-Type' => str_ends_with($path, '.webp') ? 'image/webp' : 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
