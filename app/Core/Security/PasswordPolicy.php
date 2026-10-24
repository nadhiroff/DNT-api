<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Politique de mot de passe : longueur minimale, refus des mots de passe courants
 * (liste locale), des répétitions triviales et des mots de passe dérivés de l'e-mail.
 */
final class PasswordPolicy
{
    public const int MIN_LENGTH = 10;

    public const int MAX_LENGTH = 128;

    public function __construct(private readonly string $commonPasswordsFile)
    {
    }

    /**
     * @return list<string> messages d'erreur (vide si le mot de passe est acceptable)
     */
    public function validate(string $password, ?string $email = null, ?string $name = null): array
    {
        $errors = [];
        $length = mb_strlen($password);
        if ($length < self::MIN_LENGTH) {
            $errors[] = 'Le mot de passe doit contenir au moins ' . self::MIN_LENGTH . ' caractères.';
        }
        if ($length > self::MAX_LENGTH) {
            $errors[] = 'Le mot de passe ne doit pas dépasser ' . self::MAX_LENGTH . ' caractères.';
        }
        if ($errors !== []) {
            return $errors;
        }
        $normalized = mb_strtolower($password);
        if (count(array_unique(mb_str_split($normalized))) < 4) {
            $errors[] = 'Le mot de passe est trop répétitif.';
        } elseif ($this->isCommon($normalized)) {
            $errors[] = 'Ce mot de passe fait partie des mots de passe les plus utilisés : choisissez-en un autre.';
        }
        foreach ([$email !== null ? strstr($email, '@', true) : null, $email, $name] as $personal) {
            if (is_string($personal) && mb_strlen($personal) >= 4 && str_contains($normalized, mb_strtolower($personal))) {
                $errors[] = 'Le mot de passe ne doit pas contenir votre nom ou votre adresse e-mail.';
                break;
            }
        }

        return $errors;
    }

    /**
     * Recherche dichotomique dans la liste triée (ordre binaire, minuscules ASCII, une entrée par
     * ligne) : quelques lectures disque au lieu de charger ~140 000 entrées en mémoire.
     */
    public function isCommon(string $password): bool
    {
        $needle = strtolower($password);
        $handle = is_file($this->commonPasswordsFile) ? fopen($this->commonPasswordsFile, 'rb') : false;
        if ($handle === false) {
            return false;
        }
        try {
            $stat = fstat($handle);
            if ($stat === false) {
                return false;
            }
            $low = 0;
            $high = $stat['size'];
            while ($low < $high) {
                $mid = intdiv($low + $high, 2);
                // Invariant : la cible commence dans [low, high). On se place sur la première
                // ligne qui débute à une position ≥ mid.
                fseek($handle, max(0, $mid - 1));
                if ($mid > 0) {
                    fgets($handle);
                }
                $lineStart = ftell($handle);
                $line = fgets($handle);
                if ($line === false || $lineStart === false || $lineStart >= $high) {
                    $high = $mid;
                    continue;
                }
                $cmp = strcmp(rtrim($line, "\r\n"), $needle);
                if ($cmp === 0) {
                    return true;
                }
                if ($cmp < 0) {
                    $low = (int) ftell($handle);
                } else {
                    $high = $mid;
                }
            }
            return false;
        } finally {
            fclose($handle);
        }
    }
}
