<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\View\View;
use App\Jobs\SendEmailJob;
use App\Repositories\EmailTemplateRepository;
use App\Services\Queue\Queue;

/**
 * E-mails transactionnels de la plateforme : modèle personnalisé (Administration → E-mails)
 * s'il existe, sinon gabarit par défaut de views/emails ; puis mise en file.
 */
final class PlatformMailer
{
    public function __construct(
        private readonly View $view,
        private readonly Queue $queue,
        private readonly EmailTemplateRepository $templates,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function send(string $to, string $subject, string $template, array $data = []): void
    {
        $custom = isset(EmailTemplates::PLATFORM[$template]) ? $this->templates->get(null, $template) : null;
        if ($custom !== null) {
            $rendered = $this->renderCustom($custom, $data);
            $subject = $rendered['subject'];
            $html = $rendered['html'];
        } else {
            $html = $this->view->render('emails/' . $template, ['subject' => $subject] + $data);
        }
        $message = new MailMessage($to, $subject, $html, self::toText($html));
        $this->queue->dispatch(SendEmailJob::class, $message->toArray(), 'mail');
    }

    /**
     * Rendu d'un modèle personnalisé (aussi utilisé pour l'aperçu dans l'administration).
     *
     * @param array{subject: string, heading: string, body: string, button_label: string} $template
     * @param array<string, mixed>                                                         $data
     *
     * @return array{subject: string, html: string}
     */
    public function renderCustom(array $template, array $data): array
    {
        $vars = EmailTemplates::variables($data);
        $subject = trim((string) preg_replace('/[\r\n]+/', ' ', EmailTemplates::fill($template['subject'], $vars)));
        $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', EmailTemplates::fill($template['body'], $vars)) ?: []), static fn (string $p): bool => $p !== ''));

        return [
            'subject' => $subject !== '' ? mb_substr($subject, 0, 200) : 'NovaBase',
            'html' => $this->view->render('emails/custom', [
                'subject' => $subject,
                'heading' => EmailTemplates::fill($template['heading'], $vars),
                'paragraphs' => $paragraphs,
                'buttonLabel' => EmailTemplates::fill($template['button_label'], $vars),
                'url' => $vars['lien'],
            ]),
        ];
    }

    /**
     * Version texte dérivée du HTML : liens conservés sous la forme « texte (url) ».
     */
    public static function toText(string $html): string
    {
        $body = preg_match('#<body[^>]*>(.*)</body>#is', $html, $m) === 1 ? $m[1] : $html;
        $body = (string) preg_replace('#<(style|head|title)[^>]*>.*?</\1>#is', '', $body);
        $body = (string) preg_replace_callback(
            '#<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is',
            static fn (array $l): string => trim(strip_tags($l[2])) . ' (' . html_entity_decode($l[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') . ')',
            $body,
        );
        $body = (string) preg_replace('#<(br|/p|/h[1-6]|/tr|/li|/div)[^>]*>#i', "\n", $body);
        $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace("/[ \t]+/", ' ', $text);
        $text = (string) preg_replace("/\n\s*\n\s*\n+/", "\n\n", $text);

        return trim(implode("\n", array_map('trim', explode("\n", $text))));
    }
}
