<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Jobs\SendProjectEmailJob;
use App\Repositories\EmailLogRepository;
use App\Services\Mail\MailMessage;
use App\Services\Queue\Queue;
use App\Services\UsageService;
use InvalidArgumentException;

/**
 * API d'envoi d'e-mails d'un projet : POST /email/send (clé secrète), GET /email/{id}.
 * L'envoi passe toujours par la file d'attente.
 */
final class EmailApiController
{
    private const int MAX_BODY = 15_000_000;

    public function __construct(
        private readonly EmailLogRepository $logs,
        private readonly Queue $queue,
        private readonly UsageService $usage,
    ) {
    }

    public function send(Request $request): Response
    {
        /** @var array{id: int, owner_id: int} $project */
        $project = $request->attribute('project');
        /** @var array{id: int, type: string} $key */
        $key = $request->attribute('api_key');
        if ($key['type'] !== 'secret') {
            throw new HttpException(403, 'secret_key_required', 'L\'envoi d\'e-mails exige la clé secrète (à utiliser uniquement côté serveur).');
        }
        $body = $request->json(self::MAX_BODY);
        $list = static function (mixed $v): array {
            if (is_string($v)) {
                return [trim($v)];
            }

            return is_array($v) ? array_values(array_map(static fn (mixed $x): string => is_string($x) ? trim($x) : '', $v)) : [];
        };
        $html = is_string($body['body_html'] ?? null) ? $body['body_html'] : '';
        $text = is_string($body['body_text'] ?? null) ? $body['body_text'] : '';
        if ($html === '' && $text === '') {
            throw new HttpException(422, 'validation_failed', 'body_html ou body_text est obligatoire.');
        }
        $attachments = [];
        foreach (is_array($body['attachments'] ?? null) ? $body['attachments'] : [] as $file) {
            if (!is_array($file) || !is_string($file['filename'] ?? null) || !is_string($file['content'] ?? null)) {
                throw new HttpException(422, 'validation_failed', 'Chaque pièce jointe doit avoir filename et content (base64).');
            }
            $attachments[] = ['filename' => $file['filename'], 'content' => $file['content'], 'type' => is_string($file['content_type'] ?? null) ? $file['content_type'] : 'application/octet-stream'];
        }
        try {
            $message = new MailMessage(
                $list($body['to'] ?? []),
                is_string($body['subject'] ?? null) ? $body['subject'] : '',
                $html,
                $text !== '' ? $text : strip_tags($html),
                $list($body['cc'] ?? []),
                $list($body['bcc'] ?? []),
                $attachments,
            );
        } catch (InvalidArgumentException $e) {
            throw new HttpException(422, 'validation_failed', $e->getMessage());
        }
        $this->usage->assertCanSendEmail($project, count($message->recipients()));
        $logId = $this->logs->create($project['id'], $key['id'], implode(', ', $message->recipients()), $message->subject);
        $this->queue->dispatch(SendProjectEmailJob::class, ['log_id' => $logId, 'project_id' => $project['id'], 'message' => $message->toArray()], 'mail');

        $log = $this->logs->find($project['id'], $logId);

        return Response::json(['id' => $logId, 'status' => $log['status'] ?? 'pending'], 202);
    }

    public function show(Request $request): Response
    {
        /** @var array{id: int} $project */
        $project = $request->attribute('project');
        $log = $this->logs->find($project['id'], (int) $request->route('id'));
        if ($log === null) {
            throw new HttpException(404, 'not_found', 'E-mail introuvable.');
        }

        return Response::json([
            'id' => $log['id'],
            'to' => $log['recipients'],
            'subject' => $log['subject'],
            'status' => $log['status'],
            'error' => $log['error'],
            'attempts' => $log['attempts'],
            'sent_at' => $log['sent_at'],
            'created_at' => $log['created_at'],
        ]);
    }
}
