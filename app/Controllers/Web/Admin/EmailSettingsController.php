<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Controllers\Web\CurrentUser;
use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Repositories\EmailTemplateRepository;
use App\Services\AuditService;
use App\Services\Mail\EmailTemplates;
use App\Services\Mail\MailMessage;
use App\Services\Mail\MailTransport;
use App\Services\Mail\PlatformMailer;
use App\Services\Mail\SmtpService;
use Throwable;

/**
 * E-mails de la plateforme (superadmin) : serveur SMTP global, test, modèles personnalisés.
 */
final class EmailSettingsController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly SmtpService $smtp,
        private readonly MailTransport $transport,
        private readonly EmailTemplateRepository $templates,
        private readonly PlatformMailer $mailer,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->responder->view($request, 'admin/emails', [
            'settings' => $this->smtp->stored(null),
            'effective' => $this->smtp->resolve(null),
            'presets' => SmtpService::PRESETS,
            'templates' => EmailTemplates::PLATFORM,
            'customized' => $this->templates->customizedKeys(null),
        ]);
    }

    public function save(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $this->smtp->save(null, $request->all(), true);
        $this->audit->record('admin.smtp_updated', $user['id'], 'smtp:global', $request->ip());

        return $this->responder->flash('success', 'Serveur SMTP de la plateforme enregistré.')->redirect('/admin/emails');
    }

    public function test(Request $request): Response
    {
        $data = Validator::validate($request->all(), ['test_to' => 'required|email|max:254'], ['test_to' => 'destinataire du test']);
        $html = '<p>Bonjour,</p><p>Si vous lisez ce message, le serveur SMTP de NovaBase fonctionne.</p>';
        try {
            $this->transport->send(new MailMessage((string) $data['test_to'], 'Test de configuration SMTP — NovaBase', $html, strip_tags($html)));
        } catch (Throwable $e) {
            return $this->responder->flash('error', 'Échec de l\'envoi : ' . mb_substr($e->getMessage(), 0, 300))->redirect('/admin/emails');
        }

        return $this->responder->flash('success', 'E-mail de test envoyé à ' . $data['test_to'] . '.')->redirect('/admin/emails');
    }

    public function editTemplate(Request $request): Response
    {
        $key = $this->key($request);
        $default = EmailTemplates::PLATFORM[$key];

        return $this->responder->view($request, 'admin/email-template', [
            'key' => $key,
            'definition' => $default,
            'template' => $this->templates->get(null, $key) ?? $default,
            'customized' => $this->templates->get(null, $key) !== null,
        ]);
    }

    public function saveTemplate(Request $request): Response
    {
        $key = $this->key($request);
        $data = Validator::validate($request->all(), [
            'subject' => 'required|string|max:200',
            'heading' => 'required|string|max:200',
            'body' => 'required|string|max:5000',
            'button_label' => 'nullable|string|max:80',
        ], ['subject' => 'sujet', 'heading' => 'titre', 'body' => 'message', 'button_label' => 'texte du bouton']);
        if (preg_match('/[\r\n]/', (string) $data['subject']) === 1) {
            throw \App\Core\Validation\ValidationException::withMessage('subject', 'Le sujet doit tenir sur une ligne.');
        }
        $this->templates->save(null, $key, [
            'subject' => (string) $data['subject'],
            'heading' => (string) $data['heading'],
            'body' => (string) $data['body'],
            'button_label' => is_string($data['button_label'] ?? null) ? $data['button_label'] : '',
        ]);
        $this->audit->record('admin.email_template_updated', CurrentUser::from($request)['id'], 'email_template:' . $key, $request->ip());

        return $this->responder->flash('success', 'Modèle « ' . EmailTemplates::PLATFORM[$key]['label'] . ' » enregistré.')->redirect('/admin/emails/templates/' . $key);
    }

    public function resetTemplate(Request $request): Response
    {
        $key = $this->key($request);
        $this->templates->reset(null, $key);

        return $this->responder->flash('success', 'Texte d\'origine restauré.')->redirect('/admin/emails/templates/' . $key);
    }

    /**
     * Aperçu HTML du modèle avec des valeurs d'exemple (nouvel onglet).
     */
    public function preview(Request $request): Response
    {
        $key = $this->key($request);
        $template = $this->templates->get(null, $key) ?? EmailTemplates::PLATFORM[$key];
        $rendered = $this->mailer->renderCustom($template, [
            'name' => 'Awa Diop',
            'url' => 'https://exemple.com/lien',
            'inviterName' => 'Nadhir',
            'personalMessage' => 'Viens tester NovaBase avec moi !',
        ]);

        return \App\Core\Http\Response::html($rendered['html'], 200, [
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; frame-ancestors 'none'; base-uri 'none'",
        ]);
    }

    private function key(Request $request): string
    {
        $key = (string) $request->route('key');
        if (!isset(EmailTemplates::PLATFORM[$key])) {
            throw HttpException::notFound();
        }

        return $key;
    }
}
