<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use App\Services\MaintenanceService;

/**
 * Tâches périodiques : taille des bases (quotas), purge des clés révoquées depuis 30 jours,
 * des jetons expirés et des compteurs obsolètes. Cron : toutes les 10 minutes.
 */
final class MaintenanceCommand extends Command
{
    public function __construct(private readonly MaintenanceService $maintenance)
    {
    }

    public static function name(): string
    {
        return 'maintenance';
    }

    public static function description(): string
    {
        return 'Recalcule l\'usage des quotas et purge les données expirées (cron toutes les 10 min)';
    }

    public function run(Input $input, Output $output): int
    {
        foreach ($this->maintenance->run() as $label => $count) {
            $output->line('  ' . str_pad($label, 34) . $count);
        }
        $output->success('Maintenance terminée.');

        return self::SUCCESS;
    }
}
