<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Modèles des e-mails de la plateforme : textes par défaut et variables disponibles.
 * Les textes personnalisés remplacent ces valeurs (Administration → E-mails).
 */
final class EmailTemplates
{
    /**
     * @var array<string, array{label: string, description: string, subject: string, heading: string, body: string, button_label: string, variables: array<string, string>}>
     */
    public const array PLATFORM = [
        'verify-email' => [
            'label' => 'Confirmation de l\'adresse e-mail',
            'description' => 'Envoyé après l\'inscription.',
            'subject' => 'Confirmez votre adresse e-mail',
            'heading' => 'Confirmez votre adresse e-mail',
            'body' => "Bonjour {{nom}},\n\nBienvenue ! Cliquez sur le bouton ci-dessous pour activer votre compte. Ce lien est valable 1 heure et ne peut servir qu'une fois.\n\nVous n'êtes pas à l'origine de cette inscription ? Ignorez simplement ce message.",
            'button_label' => 'Confirmer mon adresse',
            'variables' => ['nom' => 'Nom de l\'utilisateur', 'lien' => 'Lien de confirmation'],
        ],
        'reset-password' => [
            'label' => 'Mot de passe oublié',
            'description' => 'Envoyé lors d\'une demande de réinitialisation.',
            'subject' => 'Réinitialisation de votre mot de passe',
            'heading' => 'Réinitialisation du mot de passe',
            'body' => "Bonjour {{nom}},\n\nUne réinitialisation de mot de passe a été demandée pour votre compte. Ce lien est valable 1 heure et ne peut servir qu'une fois.\n\nVous n'avez rien demandé ? Ignorez ce message : votre mot de passe actuel reste valable.",
            'button_label' => 'Choisir un nouveau mot de passe',
            'variables' => ['nom' => 'Nom de l\'utilisateur', 'lien' => 'Lien de réinitialisation'],
        ],
        'password-changed' => [
            'label' => 'Mot de passe modifié',
            'description' => 'Alerte de sécurité après un changement de mot de passe.',
            'subject' => 'Votre mot de passe a été modifié',
            'heading' => 'Votre mot de passe a été modifié',
            'body' => "Bonjour {{nom}},\n\nLe mot de passe de votre compte vient d'être modifié et vos autres sessions ont été fermées.\n\nSi ce n'est pas vous, réinitialisez immédiatement votre mot de passe.",
            'button_label' => 'Réinitialiser mon mot de passe',
            'variables' => ['nom' => 'Nom de l\'utilisateur', 'lien' => 'Lien de réinitialisation'],
        ],
        'account-exists' => [
            'label' => 'Inscription avec une adresse existante',
            'description' => 'Envoyé au titulaire quand quelqu\'un tente de s\'inscrire avec son adresse.',
            'subject' => 'Tentative d\'inscription avec votre adresse',
            'heading' => 'Vous avez déjà un compte',
            'body' => "Bonjour {{nom}},\n\nQuelqu'un (probablement vous) a essayé de créer un compte avec cette adresse, qui est déjà inscrite. Vous pouvez simplement vous connecter.\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.",
            'button_label' => 'Se connecter',
            'variables' => ['nom' => 'Nom de l\'utilisateur', 'lien' => 'Lien de connexion'],
        ],
        'invitation' => [
            'label' => 'Invitation d\'un proche',
            'description' => 'Envoyé depuis Administration → Invitations.',
            'subject' => '{{invitant}} vous invite sur NovaBase',
            'heading' => '{{invitant}} vous invite sur NovaBase',
            'body' => "Vous êtes invité(e) à rejoindre NovaBase, la plateforme backend pour vos applications web et mobiles.\n\n{{message}}\n\nCe lien est valable 7 jours et ne peut servir qu'une fois.",
            'button_label' => 'Accepter l\'invitation',
            'variables' => ['invitant' => 'Nom de la personne qui invite', 'message' => 'Message personnel', 'lien' => 'Lien d\'acceptation'],
        ],
    ];

    /**
     * Variables d'un envoi à partir des données transmises aux gabarits.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    public static function variables(array $data): array
    {
        $map = [
            'nom' => $data['name'] ?? '',
            'lien' => $data['url'] ?? $data['loginUrl'] ?? $data['resetUrl'] ?? '',
            'invitant' => $data['inviterName'] ?? '',
            'message' => $data['personalMessage'] ?? '',
        ];

        return array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $map);
    }

    /**
     * @param array<string, string> $variables
     */
    public static function fill(string $text, array $variables): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static fn (array $m): string => $variables[$m[1]] ?? '', $text);
    }
}
