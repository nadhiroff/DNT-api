<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Config;
use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;

/**
 * Boîte de réception de développement (MAIL_DRIVER=log). Route enregistrée uniquement en local.
 */
final class DevMailController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly Config $config,
        private readonly string $directory,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->guard();
        $mails = [];
        foreach (array_slice(array_reverse($this->files()), 0, 50) as $file) {
            $mail = $this->read($file);
            if ($mail !== null) {
                $mails[] = ['id' => basename($file, '.mail.php')] + $mail;
            }
        }

        return $this->responder->view($request, 'dev/mailbox', ['mails' => $mails]);
    }

    public function show(Request $request): Response
    {
        $this->guard();
        $id = (string) $request->route('id');
        $mail = $this->read($this->directory . '/' . $id . '.mail.php');
        if ($mail === null) {
            throw HttpException::notFound();
        }

        // HTML d'e-mail (styles en ligne) : CSP dédiée, sans aucun script.
        return Response::html($mail['html'], 200, [
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; frame-ancestors 'none'; base-uri 'none'",
        ]);
    }

    private function guard(): void
    {
        if ($this->config->string('app.env') !== 'local' || $this->config->string('mail.driver') !== 'log') {
            throw HttpException::notFound();
        }
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = glob($this->directory . '/*.mail.php') ?: [];
        sort($files);

        return $files;
    }

    /**
     * @return array{to: string, subject: string, html: string, sent_at: string}|null
     */
    private function read(string $file): ?array
    {
        if (preg_match('/^[0-9]{8}-[0-9]{6}-[0-9]{6}[a-f0-9]{4}\.mail\.php$/', basename($file)) !== 1 || !is_file($file)) {
            return null;
        }
        $lines = explode("\n", (string) file_get_contents($file), 2);
        $data = json_decode($lines[1] ?? '', true);
        if (!is_array($data)) {
            return null;
        }

        return [
            'to' => (string) ($data['to'] ?? ''),
            'subject' => (string) ($data['subject'] ?? ''),
            'html' => (string) ($data['html'] ?? ''),
            'sent_at' => (string) ($data['sent_at'] ?? ''),
        ];
    }
}
