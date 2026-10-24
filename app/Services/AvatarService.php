<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validation\ValidationException;

/**
 * Photos de profil (témoignages) : contrôle du type réel, ré-encodage par GD en carré 160 px
 * (supprime métadonnées EXIF et tout contenu caché), nom aléatoire, stockage hors du web.
 */
final class AvatarService
{
    private const int MAX_BYTES = 2_097_152;

    private const int SIZE = 160;

    private const array ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @param array<string, mixed> $file entrée de $_FILES
     *
     * @return string nom du fichier stocké (32 hex + extension)
     *
     * @throws ValidationException
     */
    public function store(array $file, string $field = 'avatar'): string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $tmp = $file['tmp_name'] ?? null;
        $size = $file['size'] ?? 0;
        if ($error !== UPLOAD_ERR_OK || !is_string($tmp) || $tmp === '') {
            throw ValidationException::withMessage($field, $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE ? 'Image trop lourde (2 Mo maximum).' : 'Le fichier n\'a pas pu être reçu.');
        }
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp)) {
            throw ValidationException::withMessage($field, 'Fichier invalide.');
        }
        if (!is_int($size) || $size > self::MAX_BYTES || filesize($tmp) > self::MAX_BYTES) {
            throw ValidationException::withMessage($field, 'Image trop lourde (2 Mo maximum).');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw ValidationException::withMessage($field, 'Formats acceptés : JPEG, PNG, WebP ou GIF.');
        }
        $data = file_get_contents($tmp);
        $info = $data !== false ? @getimagesizefromstring($data) : false;
        if ($data === false || $info === false || $info[0] < 16 || $info[1] < 16 || $info[0] * $info[1] > 40_000_000) {
            throw ValidationException::withMessage($field, 'Image illisible ou dimensions invalides.');
        }
        $source = @imagecreatefromstring($data);
        if ($source === false) {
            throw ValidationException::withMessage($field, 'Image illisible.');
        }

        // Recadrage carré centré puis redimensionnement.
        $side = min($info[0], $info[1]);
        $target = imagecreatetruecolor(self::SIZE, self::SIZE);
        if ($target === false) {
            throw new \RuntimeException('Création de l\'image impossible.');
        }
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, intdiv($info[0] - $side, 2), intdiv($info[1] - $side, 2), self::SIZE, self::SIZE, $side, $side);

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0750, true);
        }
        $useWebp = function_exists('imagewebp');
        $name = bin2hex(random_bytes(16)) . ($useWebp ? '.webp' : '.png');
        $ok = $useWebp ? imagewebp($target, $this->directory . '/' . $name, 85) : imagepng($target, $this->directory . '/' . $name, 6);
        if (!$ok) {
            throw new \RuntimeException('Enregistrement de l\'image impossible.');
        }

        return $name;
    }

    public function delete(?string $name): void
    {
        if ($name !== null && self::isValidName($name) && is_file($this->directory . '/' . $name)) {
            unlink($this->directory . '/' . $name);
        }
    }

    public function path(string $name): ?string
    {
        return self::isValidName($name) && is_file($this->directory . '/' . $name) ? $this->directory . '/' . $name : null;
    }

    public static function isValidName(string $name): bool
    {
        return preg_match('/^[a-f0-9]{32}\.(webp|png)$/', $name) === 1;
    }
}
