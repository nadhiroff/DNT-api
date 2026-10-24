<?php

declare(strict_types=1);

namespace App\Core\Console;

use App\Core\Application;
use App\Core\Log\Logger;
use Throwable;

/**
 * Noyau de bin/console : enregistre et exécute les commandes.
 */
final class Kernel
{
    /** @var array<string, class-string<Command>> */
    private array $commands = [];

    /**
     * @param list<class-string<Command>> $commands
     */
    public function __construct(private readonly Application $app, array $commands)
    {
        foreach ($commands as $class) {
            $this->commands[$class::name()] = $class;
        }
    }

    /**
     * @param list<string> $argv argv complet (argv[0] = script)
     */
    public function handle(array $argv, ?Output $output = null, ?Input $input = null): int
    {
        $output ??= new Output();
        $name = $argv[1] ?? 'list';
        $input ??= new Input(array_slice($argv, 2));

        if ($name === 'list' || $name === '--help' || $name === '-h') {
            $this->list($output);

            return Command::SUCCESS;
        }
        if ($name === 'help') {
            $target = $input->argument(0);
            if ($target === null || !isset($this->commands[$target])) {
                $this->list($output);

                return Command::SUCCESS;
            }
            $command = $this->commands[$target];
            $output->line($output->bold($command::name()) . ' — ' . $command::description());
            if ($command::usage() !== '') {
                $output->line();
                $output->line($command::usage());
            }

            return Command::SUCCESS;
        }
        if (!isset($this->commands[$name])) {
            $output->error('Commande inconnue : ' . $name);
            $this->list($output);

            return Command::FAILURE;
        }

        try {
            return $this->instantiate($this->commands[$name])->run($input, $output);
        } catch (Throwable $e) {
            $this->app->container()->get(Logger::class)->error('Échec de la commande ' . $name, ['exception' => $e]);
            $output->error($e->getMessage());
            if ($this->app->config()->bool('app.debug')) {
                $output->line($output->muted($e->getTraceAsString()));
            }

            return Command::FAILURE;
        }
    }

    private function list(Output $output): void
    {
        $output->line($output->bold('NovaBase') . ' — console d\'administration');
        $output->line();
        $output->line('Usage : php bin/console <commande> [options]');
        $output->line();
        $rows = [];
        foreach ($this->commands as $class) {
            $rows[] = [$class::name(), $class::description()];
        }
        sort($rows);
        $output->table(['Commande', 'Description'], $rows);
    }

    /**
     * @param class-string<Command> $class
     */
    private function instantiate(string $class): Command
    {
        return $this->app->container()->get($class);
    }
}
