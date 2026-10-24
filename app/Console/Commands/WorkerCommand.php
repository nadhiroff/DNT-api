<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use App\Core\Database\DatabaseManager;
use App\Services\Queue\Worker;
use App\Services\ServerMetrics;

/**
 * Worker de la file d'attente, à superviser par systemd ou Supervisor (redémarrage automatique).
 */
final class WorkerCommand extends Command
{
    private bool $stop = false;

    public function __construct(
        private readonly Worker $worker,
        private readonly DatabaseManager $databases,
        private readonly ServerMetrics $metrics,
    ) {
    }

    public static function name(): string
    {
        return 'worker';
    }

    public static function description(): string
    {
        return 'Exécute les tâches de fond (envoi d\'e-mails…)';
    }

    public static function usage(): string
    {
        return "Options :\n"
            . "  --queue=mail,default  Files à traiter, par priorité (défaut : mail,default)\n"
            . "  --once                Traite les tâches disponibles puis s'arrête (cron)\n"
            . "  --sleep=3             Pause en secondes quand la file est vide\n"
            . "  --max-jobs=1000       Redémarre après N tâches (libère la mémoire)\n"
            . '  --max-time=3600       Redémarre après N secondes';
    }

    public function run(Input $input, Output $output): int
    {
        $queues = array_values(array_filter(array_map('trim', explode(',', $input->option('queue') ?? 'mail,default'))));
        $sleep = max(1, (int) ($input->option('sleep') ?? 3));
        $maxJobs = max(1, (int) ($input->option('max-jobs') ?? 1000));
        $deadline = time() + max(60, (int) ($input->option('max-time') ?? 3600));
        $once = $input->flag('once');

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            $handler = function (): void {
                $this->stop = true;
            };
            pcntl_signal(SIGTERM, $handler);
            pcntl_signal(SIGINT, $handler);
        }

        $output->info('Worker démarré sur : ' . implode(', ', $queues));
        $processed = 0;
        $lastMetrics = 0;
        while (!$this->stop && $processed < $maxJobs && time() < $deadline) {
            if (time() - $lastMetrics >= 60) {
                $this->metrics->collect();
                $lastMetrics = time();
            }
            $didWork = false;
            foreach ($queues as $queue) {
                if ($this->worker->runNext($queue)) {
                    ++$processed;
                    $didWork = true;
                    break;
                }
            }
            if (!$didWork) {
                if ($once) {
                    break;
                }
                // Connexion fermée pendant l'attente : pas de connexion inactive conservée.
                $this->databases->disconnect();
                sleep($sleep);
            }
        }
        $output->success($processed . ' tâche(s) traitée(s).');

        return self::SUCCESS;
    }
}
