<?php

declare(strict_types=1);

namespace App\Core\Database;

use App\Core\Config;

/**
 * Point unique d'accès aux connexions : une connexion paresseuse par base, réutilisée
 * pendant toute la requête (ou la tâche du worker).
 */
final class DatabaseManager
{
    private ?Database $platform = null;

    private ?Database $admin = null;

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Compte applicatif (DML sur la base plateforme).
     */
    public function platform(): Database
    {
        /** @var array{host?: string, port?: int|string, database?: string|null, username?: string, password?: string, socket?: string|null} $config */
        $config = $this->config->array('database.platform');

        return $this->platform ??= new Database(static fn (): \PDO => ConnectionFactory::make($config));
    }

    /**
     * Compte d'administration (DDL, création des bases et utilisateurs des projets).
     * Réservé aux migrations et au ProjectProvisioner.
     */
    public function admin(): Database
    {
        /** @var array{host?: string, port?: int|string, database?: string|null, username?: string, password?: string, socket?: string|null} $config */
        $config = $this->config->array('database.admin');

        return $this->admin ??= new Database(static fn (): \PDO => ConnectionFactory::make($config));
    }

    public function disconnect(): void
    {
        $this->platform = null;
        $this->admin = null;
    }
}
