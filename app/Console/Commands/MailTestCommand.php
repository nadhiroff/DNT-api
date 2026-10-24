<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Config;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use App\Core\Validation\Validator;
use App\Services\Mail\MailMessage;
use App\Services\Mail\MailTransport;

/**
 * Envoie immédiatement un e-mail de test pour valider la configuration SMTP globale.
 */
final class MailTestCommand extends Command
{
    public function __construct(
        private readonly MailTransport $transport,
        private readonly Config $config,
    ) {
    }

    public static function name(): string
    {
        return 'mail:test';
    }

    public static function description(): string
    {
        return 'Envoie un e-mail de test avec le SMTP de config/env.php';
    }

    public static function usage(): string
    {
        return "Usage : php bin/console mail:test destinataire@exemple.com";
    }

    public function run(Input $input, Output $output): int
    {
        $to = $input->argument(0) ?? '';
        if (!Validator::isEmail($to)) {
            $output->error('Indiquez une adresse e-mail valide : php bin/console mail:test vous@exemple.com');

            return self::FAILURE;
        }
        $output->info(sprintf(
            'Envoi via %s:%d (%s) en tant que %s…',
            $this->config->string('mail.host'),
            $this->config->int('mail.port'),
            $this->config->string('mail.encryption'),
            $this->config->string('mail.username') !== '' ? $this->config->string('mail.username') : '(sans authentification)',
        ));
        $html = '<p>Bonjour,</p><p>Si vous lisez ce message, la configuration SMTP de NovaBase fonctionne.</p>';
        $this->transport->send(new MailMessage($to, 'Test de configuration SMTP — NovaBase', $html, strip_tags($html)));
        $output->success('E-mail envoyé à ' . $to . '. Vérifiez aussi le dossier « Spam ».');

        return self::SUCCESS;
    }
}
