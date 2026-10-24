<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Config;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use App\Core\Database\Database;
use App\Core\Security\PasswordHasher;
use App\Core\Security\PasswordPolicy;
use App\Core\Security\Totp;
use App\Core\Validation\Validator;
use App\Repositories\RecoveryCodeRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\Crypto;
use App\Services\RecoveryCodeService;

/**
 * Création du compte superadmin — uniquement en ligne de commande, 2FA TOTP obligatoire.
 */
final class CreateSuperadminCommand extends Command
{
    private const int MAX_TOTP_ATTEMPTS = 3;

    public function __construct(
        private readonly Database $db,
        private readonly UserRepository $users,
        private readonly RecoveryCodeRepository $recoveryCodes,
        private readonly RecoveryCodeService $recoveryCodeService,
        private readonly AuditService $audit,
        private readonly PasswordHasher $hasher,
        private readonly PasswordPolicy $policy,
        private readonly Crypto $crypto,
        private readonly Config $config,
        private readonly Totp $totp,
    ) {
    }

    public static function name(): string
    {
        return 'create-superadmin';
    }

    public static function description(): string
    {
        return 'Crée un compte superadmin (2FA TOTP obligatoire)';
    }

    public static function usage(): string
    {
        return "Options :\n"
            . "  --email=<e-mail>   Adresse e-mail (demandée sinon)\n"
            . "  --name=<nom>       Nom affiché (demandé sinon)\n"
            . "  --password-stdin   Lit le mot de passe sur l'entrée standard (automatisation)\n\n"
            . "Le mot de passe n'est jamais accepté en argument (il apparaîtrait dans l'historique du shell\n"
            . 'et la liste des processus). La 2FA est activée et vérifiée immédiatement.';
    }

    public function run(Input $input, Output $output): int
    {
        $output->info('Création d\'un compte superadmin NovaBase');
        $output->line();

        $email = $input->option('email') ?? $input->ask($output, 'Adresse e-mail');
        $name = $input->option('name') ?? $input->ask($output, 'Nom affiché', 'Superadmin');
        $data = Validator::validate(['email' => $email, 'name' => $name], [
            'email' => 'required|email|max:254',
            'name' => 'required|string|min:2|max:120',
        ], ['email' => 'adresse e-mail', 'name' => 'nom']);
        /** @var string $email */
        $email = $data['email'];
        /** @var string $name */
        $name = $data['name'];

        if ($this->users->emailExists($email)) {
            $output->error('Un compte existe déjà avec cette adresse e-mail.');

            return self::FAILURE;
        }

        $password = $this->askPassword($input, $output, $email, $name);
        if ($password === null) {
            return self::FAILURE;
        }

        // Enrôlement 2FA : le secret n'est enregistré qu'après validation d'un premier code.
        $totp = $this->totp;
        $secret = $totp->newSecret();
        $issuer = $this->config->string('security.totp_issuer', 'NovaBase');
        $output->line();
        $output->info('Authentification à deux facteurs (obligatoire)');
        $output->line('Ajoutez ce compte dans votre application d\'authentification (Aegis, Google Authenticator, 1Password…) :');
        $output->line('  Clé secrète : ' . $output->bold(trim(chunk_split($secret, 4, ' '))));
        $output->line('  Ou l\'URI   : ' . $totp->provisioningUri($secret, $email, $issuer));
        $output->line();

        $step = null;
        for ($attempt = 1; $attempt <= self::MAX_TOTP_ATTEMPTS && $step === null; ++$attempt) {
            $code = $input->ask($output, 'Code à 6 chiffres affiché par l\'application');
            $step = $totp->verify($secret, $code);
            if ($step === null) {
                $output->warning('Code invalide.' . ($attempt < self::MAX_TOTP_ATTEMPTS ? ' Vérifiez l\'heure de votre téléphone et réessayez.' : ''));
            }
        }
        if ($step === null) {
            $output->error('Échec de la vérification 2FA : aucun compte n\'a été créé.');

            return self::FAILURE;
        }

        $codes = $this->recoveryCodeService->generate();
        $userId = $this->db->transaction(function () use ($email, $name, $password, $secret, $step, $codes): int {
            $id = $this->users->create([
                'email' => $email,
                'password_hash' => $this->hasher->hash($password),
                'name' => $name,
                'role' => 'superadmin',
                // Aucun plan : le rôle superadmin n'est soumis à aucune limite.
                'plan_id' => null,
                'status' => 'active',
                'email_verified' => true,
                'totp_secret_enc' => $this->crypto->encrypt($secret, 'totp_secret'),
            ]);
            $this->users->setTotpLastStep($id, $step);
            $this->recoveryCodes->replace($id, array_map($this->recoveryCodeService->hash(...), $codes));
            $this->audit->record('superadmin.created', null, 'user:' . $id, null, ['email' => $email, 'via' => 'cli'], 'cli');

            return $id;
        });

        $output->line();
        $output->success('Superadmin créé (id ' . $userId . ').');
        $output->line();
        $output->warning('Codes de secours — affichés UNE SEULE FOIS. Conservez-les hors ligne :');
        foreach (array_chunk($codes, 2) as $pair) {
            $output->line('    ' . implode('      ', $pair));
        }
        $output->line();

        return self::SUCCESS;
    }

    private function askPassword(Input $input, Output $output, string $email, string $name): ?string
    {
        if ($input->flag('password-stdin')) {
            $password = $input->readLine() ?? '';
            $errors = $this->policy->validate($password, $email, $name);
            foreach ($errors as $error) {
                $output->error($error);
            }

            return $errors === [] ? $password : null;
        }

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $password = $input->secret($output, 'Mot de passe (10 caractères minimum)');
            $errors = $this->policy->validate($password, $email, $name);
            if ($errors !== []) {
                foreach ($errors as $error) {
                    $output->warning($error);
                }
                continue;
            }
            if (!hash_equals($password, $input->secret($output, 'Confirmez le mot de passe'))) {
                $output->warning('Les mots de passe ne correspondent pas.');
                continue;
            }

            return $password;
        }
        $output->error('Trop de tentatives.');

        return null;
    }
}
