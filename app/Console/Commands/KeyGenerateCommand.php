<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application;
use App\Core\Console\Command;
use App\Core\Console\EnvFile;
use App\Core\Console\Input;
use App\Core\Console\Output;
use App\Services\Crypto;

final class KeyGenerateCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
    }

    public static function name(): string
    {
        return 'key:generate';
    }

    public static function description(): string
    {
        return 'Génère la clé maître APP_KEY dans config/env.php';
    }

    public static function usage(): string
    {
        return "Options :\n  --show   Affiche une clé sans modifier config/env.php\n  --force  Remplace une clé existante (rend illisibles les secrets déjà chiffrés !)";
    }

    public function run(Input $input, Output $output): int
    {
        $key = Crypto::generateKey();
        if ($input->flag('show')) {
            $output->line($key);

            return self::SUCCESS;
        }
        $env = new EnvFile($this->app->basePath('config/env.php'));
        if (!$env->exists()) {
            $output->error('Fichier config/env.php absent : exécutez d\'abord « php install.php ».');

            return self::FAILURE;
        }
        $current = $env->get('APP_KEY');
        if ($current !== null && $current !== '' && !$input->flag('force')) {
            $output->warning('APP_KEY est déjà définie. La remplacer rendrait illisibles tous les secrets chiffrés (SMTP, MySQL des projets, 2FA…).');
            $output->line('Utilisez --force uniquement sur une installation vierge.');

            return self::FAILURE;
        }
        $env->set('APP_KEY', $key);
        $output->success('Clé maître générée dans config/env.php. Sauvegardez-la hors du serveur : sans elle, les secrets chiffrés sont perdus.');

        return self::SUCCESS;
    }
}
