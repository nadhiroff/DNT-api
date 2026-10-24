<?php

declare(strict_types=1);

namespace App\Services\Data;

/**
 * Document OpenAPI 3.1 d'un projet, généré à partir du schéma réel de ses tables.
 *
 * @phpstan-import-type TableDef from \App\Repositories\TableRepository
 */
final class OpenApiGenerator
{
    /**
     * @param array{ref: string, name: string} $project
     * @param list<TableDef>                   $tables
     *
     * @return array<string, mixed>
     */
    public static function generate(array $project, array $tables, string $baseUrl): array
    {
        $paths = [];
        $schemas = [];
        $error = ['$ref' => '#/components/schemas/Error'];
        $errors = [
            '400' => ['description' => 'Requête invalide', 'content' => ['application/json' => ['schema' => $error]]],
            '401' => ['description' => 'Clé API absente ou invalide', 'content' => ['application/json' => ['schema' => $error]]],
            '402' => ['description' => 'Quota dépassé', 'content' => ['application/json' => ['schema' => $error]]],
            '403' => ['description' => 'Interdit par les règles d\'accès', 'content' => ['application/json' => ['schema' => $error]]],
            '429' => ['description' => 'Trop de requêtes', 'content' => ['application/json' => ['schema' => $error]]],
        ];
        foreach ($tables as $table) {
            $name = $table['name'];
            $schema = ucfirst(str_replace('_', '', ucwords($name, '_')));
            $properties = [];
            $required = [];
            foreach (ProjectDataService::columns($table) as $column => $def) {
                $properties[$column] = self::typeOf($def['type']) + ($def['writable'] ? [] : ['readOnly' => true]);
                if ($def['writable'] && !$def['nullable']) {
                    $required[] = $column;
                }
            }
            $schemas[$schema] = ['type' => 'object', 'properties' => $properties] + ($required !== [] ? ['required' => $required] : []);
            $ref = ['$ref' => '#/components/schemas/' . $schema];
            $idParam = ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
            $paths['/data/' . $name] = [
                'get' => [
                    'tags' => [$name],
                    'summary' => 'Lister les lignes de ' . $name,
                    'parameters' => [
                        ['name' => 'select', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Colonnes séparées par des virgules'],
                        ['name' => 'order', 'in' => 'query', 'schema' => ['type' => 'string'], 'example' => 'created_at.desc'],
                        ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'maximum' => 100, 'default' => 20]],
                        ['name' => 'offset', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 0]],
                    ],
                    'responses' => ['200' => ['description' => 'Lignes (en-tête X-Total-Count)', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => $ref]]]]] + $errors,
                ],
                'post' => [
                    'tags' => [$name],
                    'summary' => 'Insérer une ligne (objet) ou plusieurs (tableau, 500 max)',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['oneOf' => [$ref, ['type' => 'array', 'items' => $ref, 'maxItems' => 500]]]]]],
                    'responses' => ['201' => ['description' => 'Créé', 'content' => ['application/json' => ['schema' => $ref]]]] + $errors,
                ],
            ];
            $paths['/data/' . $name . '/{id}'] = [
                'parameters' => [$idParam],
                'get' => ['tags' => [$name], 'summary' => 'Lire une ligne', 'responses' => ['200' => ['description' => 'Ligne', 'content' => ['application/json' => ['schema' => $ref]]], '404' => ['description' => 'Introuvable']] + $errors],
                'patch' => ['tags' => [$name], 'summary' => 'Modifier certaines colonnes', 'requestBody' => ['content' => ['application/json' => ['schema' => $ref]]], 'responses' => ['200' => ['description' => 'Ligne modifiée', 'content' => ['application/json' => ['schema' => $ref]]]] + $errors],
                'put' => ['tags' => [$name], 'summary' => 'Remplacer la ligne', 'requestBody' => ['content' => ['application/json' => ['schema' => $ref]]], 'responses' => ['200' => ['description' => 'Ligne remplacée', 'content' => ['application/json' => ['schema' => $ref]]]] + $errors],
                'delete' => ['tags' => [$name], 'summary' => 'Supprimer la ligne', 'responses' => ['204' => ['description' => 'Supprimée']] + $errors],
            ];
        }
        $paths['/email/send'] = ['post' => [
            'tags' => ['email'],
            'summary' => 'Envoyer un e-mail (clé secrète uniquement)',
            'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['to', 'subject'], 'properties' => [
                'to' => ['oneOf' => [['type' => 'string', 'format' => 'email'], ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'email']]]],
                'cc' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'email']],
                'bcc' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'email']],
                'subject' => ['type' => 'string', 'maxLength' => 200],
                'body_html' => ['type' => 'string'],
                'body_text' => ['type' => 'string'],
                'attachments' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['filename' => ['type' => 'string'], 'content' => ['type' => 'string', 'contentEncoding' => 'base64'], 'content_type' => ['type' => 'string']]]],
            ]]]]],
            'responses' => ['202' => ['description' => 'Mis en file d\'attente']] + $errors,
        ]];
        $schemas['Error'] = ['type' => 'object', 'properties' => ['error' => ['type' => 'object', 'properties' => ['code' => ['type' => 'string'], 'message' => ['type' => 'string'], 'details' => ['type' => 'object']]]]];

        return [
            'openapi' => '3.1.0',
            'info' => ['title' => $project['name'] . ' — API NovaBase', 'version' => '1.0.0', 'description' => 'Filtres : ?colonne=opérateur.valeur (eq, neq, gt, gte, lt, lte, like, ilike, in, is).'],
            'servers' => [['url' => $baseUrl]],
            'security' => [['apikey' => []]],
            'paths' => $paths,
            'components' => ['securitySchemes' => ['apikey' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'apikey']], 'schemas' => $schemas],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function typeOf(string $type): array
    {
        return match ($type) {
            'integer', 'bigint', 'foreign' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'decimal' => ['type' => 'string', 'format' => 'decimal'],
            'boolean' => ['type' => 'boolean'],
            'date' => ['type' => 'string', 'format' => 'date'],
            'datetime' => ['type' => 'string', 'format' => 'date-time'],
            'json' => ['type' => ['object', 'array']],
            'uuid' => ['type' => 'string', 'format' => 'uuid'],
            default => ['type' => 'string'],
        };
    }
}
