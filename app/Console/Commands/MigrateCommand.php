<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application;
use App\Core\Config;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use App\Core\Database\ConnectionFactory;
use App\Core\Database\Database;
use App\Core\Database\Identifier;
use App\Core\Database\Migrator;

/**
 * Applique les migrations de la base plateforme avec le compte d'administration.
 */
final class MigrateCommand extends Command
{
    public function __construct(
        private readonly Application $app,
        private readonly Config $config,
    ) {
    }

    public static function name(): string
    {
        return 'migrate';
    }

    public static function description(): string
    {
        return 'Crée la base plateforme si besoin et applique les migrations en attente';
    }

    public static function usage(): string
    {
        return "Options :\n  --status  Affiche l'état des migrations sans rien appliquer";
    }

    public function run(Input $input, Output $output): int
    {
        $db = $this->connect();
        $migrator = new Migrator($db, $this->app->basePath('database/migrations'));

        if ($input->flag('status')) {
            $rows = array_map(static fn (array $s): array => [$s['migration'], match ($s['state']) {
                'applied' => 'appliquée',
                'modified' => 'MODIFIÉE après application',
                default => 'en attente',
            }], $migrator->status());
            $output->table(['Migration', 'État'], $rows);

            return self::SUCCESS;
        }

        $applied = $migrator->migrate(static function (string $name) use ($output): void {
            $output->success($name);
        });
        $output->info($applied === [] ? 'Rien à migrer : la base est à jour.' : count($applied) . ' migration(s) appliquée(s).');

        return self::SUCCESS;
    }

    private function connect(): Database
    {
        /** @var array{host?: string, port?: int|string, database?: string|null, username?: string, password?: string, socket?: string|null} $admin */
        $admin = $this->config->array('database.admin');
        $name = $this->config->string('database.platform_database', 'novabase');
        $server = new Database(ConnectionFactory::make(['database' => null] + $admin));
        $server->execute('CREATE DATABASE IF NOT EXISTS ' . Identifier::quote($name) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        $server->pdo()->exec('USE ' . Identifier::quote($name));

        return $server;
    }
}
