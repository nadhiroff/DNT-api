<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use App\Services\ServerMetrics;

/**
 * Mesure RAM, cœurs et uptime pour le tableau de bord (utile quand le serveur web n'a pas accès à /proc).
 * Cron : * * * * * php /chemin/bin/console metrics:collect
 */
final class MetricsCollectCommand extends Command
{
    public function __construct(private readonly ServerMetrics $metrics)
    {
    }

    public static function name(): string
    {
        return 'metrics:collect';
    }

    public static function description(): string
    {
        return 'Mesure RAM / CPU / uptime pour le tableau de bord (cron chaque minute)';
    }

    public function run(Input $input, Output $output): int
    {
        if (!$this->metrics->collect()) {
            $output->error('Impossible d\'écrire l\'instantané dans storage/cache.');

            return self::FAILURE;
        }
        $output->success('Métriques serveur enregistrées.');

        return self::SUCCESS;
    }
}
