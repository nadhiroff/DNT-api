<?php

declare(strict_types=1);

namespace App\Core\Validation;

/**
 * Validation déclarative : « required|email|max:255 ».
 * Seuls les champs déclarés sont retournés (protection contre l'assignation de masse).
 *
 * Règles : required, nullable, string, email, integer, numeric, boolean, array, min:n, max:n,
 * between:a,b, in:a,b,c, regex:/…/ (forme tableau uniquement), same:champ, alpha_dash,
 * url, date, uuid, digits:n.
 */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /**
     * @param array<string, mixed>                       $data
     * @param array<string, string|list<string>>         $rules
     * @param array<string, string>                      $labels libellés lisibles des champs
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $labels = [],
    ) {
    }

    /**
     * @param array<string, mixed>               $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string>              $labels
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $data, array $rules, array $labels = []): array
    {
        return (new self($data, $rules, $labels))->validated();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validated(): array
    {
        if (!$this->passes()) {
            $old = array_filter(
                array_intersect_key($this->data, $this->rules),
                static fn (mixed $v, string $k): bool => !str_contains($k, 'password') && is_scalar($v),
                ARRAY_FILTER_USE_BOTH,
            );

            throw new ValidationException($this->errors, $old);
        }

        return $this->validated;
    }

    public function passes(): bool
    {
        $this->errors = [];
        $this->validated = [];
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $this->validateField($field, $rules);
        }

        return $this->errors === [];
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param list<string> $rules
     */
    private function validateField(string $field, array $rules): void
    {
        $present = array_key_exists($field, $this->data);
        $value = $present ? $this->data[$field] : null;
        if (is_string($value)) {
            $value = trim($value);
        }
        $empty = $value === null || $value === '' || $value === [];

        if (in_array('required', $rules, true) && $empty) {
            $this->addError($field, 'Le champ :field est obligatoire.');

            return;
        }
        if ($empty) {
            if (in_array('nullable', $rules, true) && $present) {
                $this->validated[$field] = null;
            } elseif (in_array('boolean', $rules, true)) {
                // Case à cocher non envoyée = false.
                $this->validated[$field] = false;
            }

            return;
        }

        $isNumericRule = in_array('integer', $rules, true) || in_array('numeric', $rules, true);
        foreach ($rules as $rule) {
            $parts = explode(':', $rule, 2);
            $name = $parts[0];
            $parameter = $parts[1] ?? null;
            $error = $this->check($name, $parameter, $value, $isNumericRule);
            if ($error !== null) {
                $this->addError($field, $error);

                return;
            }
            $value = $this->cast($name, $value);
        }
        $this->validated[$field] = $value;
    }

    private function check(string $rule, ?string $parameter, mixed $value, bool $numeric): ?string
    {
        return match ($rule) {
            'required', 'nullable' => null,
            'string' => is_string($value) ? null : 'Le champ :field doit être une chaîne de caractères.',
            'email' => is_string($value) && self::isEmail($value) ? null : 'Le champ :field doit être une adresse e-mail valide.',
            'integer' => (is_int($value) || (is_string($value) && preg_match('/^-?\d{1,19}$/', $value) === 1)) ? null : 'Le champ :field doit être un nombre entier.',
            'numeric' => is_numeric($value) ? null : 'Le champ :field doit être un nombre.',
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1', 'on', 'off', 'true', 'false', 'yes', 'no'], true) ? null : 'Le champ :field doit être vrai ou faux.',
            'array' => is_array($value) ? null : 'Le champ :field doit être une liste.',
            'min' => $this->size($value, $numeric) >= (float) $parameter ? null : $this->sizeMessage('min', (string) $parameter, $value, $numeric),
            'max' => $this->size($value, $numeric) <= (float) $parameter ? null : $this->sizeMessage('max', (string) $parameter, $value, $numeric),
            'between' => $this->between($value, (string) $parameter, $numeric),
            'digits' => is_string($value) && preg_match('/^\d{' . (int) $parameter . '}$/', $value) === 1 ? null : 'Le champ :field doit contenir ' . (int) $parameter . ' chiffres.',
            'in' => is_scalar($value) && in_array((string) $value, explode(',', (string) $parameter), true) ? null : 'La valeur du champ :field n\'est pas autorisée.',
            'regex' => is_string($value) && $parameter !== null && preg_match($parameter, $value) === 1 ? null : 'Le format du champ :field est invalide.',
            'same' => $value === ($this->data[(string) $parameter] ?? null) || (is_string($this->data[(string) $parameter] ?? null) && $value === trim((string) $this->data[(string) $parameter])) ? null : 'Les deux champs ne correspondent pas.',
            'alpha_dash' => is_string($value) && preg_match('/^[\pL\pN_\-]+$/u', $value) === 1 ? null : 'Le champ :field ne peut contenir que des lettres, chiffres, tirets et soulignés.',
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $value) === 1 ? null : 'Le champ :field doit être une URL http(s) valide.',
            'date' => is_string($value) && self::isDate($value) ? null : 'Le champ :field doit être une date valide.',
            'uuid' => is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1 ? null : 'Le champ :field doit être un UUID valide.',
            default => throw new \InvalidArgumentException('Règle de validation inconnue : ' . $rule),
        };
    }

    private function cast(string $rule, mixed $value): mixed
    {
        return match ($rule) {
            'integer' => (int) $value,
            'boolean' => in_array($value, [true, 1, '1', 'on', 'true', 'yes'], true),
            'email' => is_string($value) ? mb_strtolower($value) : $value,
            default => $value,
        };
    }

    public static function isEmail(string $value): bool
    {
        // Refus explicite des retours à la ligne (injection d'en-têtes) en plus du filtre natif.
        return strlen($value) <= 254
            && preg_match('/[\r\n\0]/', $value) !== 1
            && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function isDate(string $value): bool
    {
        foreach (['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    private function size(mixed $value, bool $numeric): float
    {
        return match (true) {
            $numeric && is_numeric($value) => (float) $value,
            is_array($value) => (float) count($value),
            is_string($value) => (float) mb_strlen($value),
            is_int($value), is_float($value) => (float) $value,
            default => 0.0,
        };
    }

    private function sizeMessage(string $kind, string $limit, mixed $value, bool $numeric): string
    {
        if ($numeric) {
            return $kind === 'min' ? 'Le champ :field doit être supérieur ou égal à ' . $limit . '.' : 'Le champ :field doit être inférieur ou égal à ' . $limit . '.';
        }
        if (is_array($value)) {
            return $kind === 'min' ? 'Le champ :field doit contenir au moins ' . $limit . ' éléments.' : 'Le champ :field ne doit pas contenir plus de ' . $limit . ' éléments.';
        }

        return $kind === 'min' ? 'Le champ :field doit contenir au moins ' . $limit . ' caractères.' : 'Le champ :field ne doit pas dépasser ' . $limit . ' caractères.';
    }

    private function between(mixed $value, string $parameter, bool $numeric): ?string
    {
        [$min, $max] = array_map('floatval', array_pad(explode(',', $parameter, 2), 2, '0'));
        $size = $this->size($value, $numeric);

        return $size >= $min && $size <= $max ? null : 'Le champ :field doit être compris entre ' . $min . ' et ' . $max . ($numeric ? '.' : ' caractères.');
    }

    private function addError(string $field, string $message): void
    {
        $label = $this->labels[$field] ?? str_replace('_', ' ', $field);
        $this->errors[$field][] = str_replace(':field', '« ' . $label . ' »', $message);
    }
}
