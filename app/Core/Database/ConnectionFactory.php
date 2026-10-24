<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;

/**
 * Fabrique de connexions PDO durcies. Une connexion par base et par requête, réutilisée,
 * jamais persistante.
 */
final class ConnectionFactory
{
    /**
     * @param array{host?: string, port?: int|string, database?: string|null, username?: string, password?: string, socket?: string|null} $config
     */
    public static function make(array $config): PDO
    {
        $dsn = 'mysql:';
        $socket = $config['socket'] ?? null;
        if (is_string($socket) && $socket !== '') {
            $dsn .= 'unix_socket=' . $socket;
        } else {
            $dsn .= 'host=' . ($config['host'] ?? '127.0.0.1') . ';port=' . (int) ($config['port'] ?? 3306);
        }
        $database = $config['database'] ?? null;
        if (is_string($database) && $database !== '') {
            if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $database) !== 1) {
                throw new \InvalidArgumentException('Nom de base de données invalide.');
            }
            $dsn .= ';dbname=' . $database;
        }
        $dsn .= ';charset=utf8mb4';

        $initCommand = class_exists(\Pdo\Mysql::class) ? \Pdo\Mysql::ATTR_INIT_COMMAND : PDO::MYSQL_ATTR_INIT_COMMAND;

        return new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 5,
            $initCommand => "SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci, time_zone = '+00:00', "
                . "sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY'",
        ]);
    }
}
