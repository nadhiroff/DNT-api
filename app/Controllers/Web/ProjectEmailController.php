<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Repositories\EmailLogRepository;
use App\Services\AuditService;
use App\Services\Mail\MailMessage;
use App\Services\Mail\MailTransport;
use App\Services\Mail\SmtpService;
use App\Services\Projects\ProjectService;
use Throwable;

/**
 * Onglet « E-mails » d'un projet : SMTP du projet, e-mail de test, historique des envois.
 *
 * @phpstan-import-type ProjectRow from \App\Repositories\ProjectRepository
 */
final class ProjectEmailController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly ProjectService $projects,
        private readonly SmtpService $smtp,
        private readonly MailTransport $transport,
        private readonly EmailLogRepository $logs,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        $project = $this->project($request);
        $status = is_string($request->query('status')) && in_array($request->query('status'), ['pending', 'sent', 'failed'], true) ? (string) $request->query('status') : '';

        return $this->responder->view($request, 'projects/emails', [
            'project' => $project,
            'tab' => 'emails',
            'settings' => $this->smtp->stored($project['id']),
            'effective' => $this->smtp->resolve($project['id']),
            'logs' => $this->logs->latest($project['id'], $status),
            'status' => $status,
            'presets' => SmtpService::PRESETS,
        ]);
    }

    public function save(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        // Le superadmin peut viser un serveur interne ; les autres sont protégés contre le SSRF.
        $this->smtp->save($project['id'], $request->all(), $user['role'] === 'superadmin');
        $this->audit->record('project.smtp_updated', $user['id'], 'project:' . $project['ref'], $request->ip());

        return $this->responder->flash('success', 'Serveur SMTP du projet enregistré. Envoyez un e-mail de test pour vérifier.')->redirect('/projects/' . $project['ref'] . '/emails');
    }

    public function remove(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $this->smtp->delete($project['id']);
        $this->audit->record('project.smtp_removed', $user['id'], 'project:' . $project['ref'], $request->ip());

        return $this->responder->flash('success', 'Le projet utilise de nouveau le SMTP de la plateforme.')->redirect('/projects/' . $project['ref'] . '/emails');
    }

    public function test(Request $request): Response
    {
        $project = $this->project($request);
        $data = Validator::validate($request->all(), ['test_to' => 'required|email|max:254'], ['test_to' => 'destinataire du test']);
        $html = '<p>Bonjour,</p><p>Cet e-mail de test confirme que le projet <strong>' . e($project['name']) . '</strong> peut envoyer des e-mails.</p>';
        try {
            // Envoi direct (et non via la file) pour afficher immédiatement le résultat.
            $this->transport->send(new MailMessage((string) $data['test_to'], 'Test SMTP — ' . $project['name'], $html, strip_tags($html)), $project['id']);
        } catch (Throwable $e) {
            return $this->responder->flash('error', 'Échec de l\'envoi : ' . mb_substr($e->getMessage(), 0, 300))->redirect('/projects/' . $project['ref'] . '/emails');
        }

        return $this->responder->flash('success', 'E-mail de test envoyé à ' . $data['test_to'] . '. Pensez à vérifier les indésirables.')->redirect('/projects/' . $project['ref'] . '/emails');
    }

    /**
     * @return ProjectRow
     */
    private function project(Request $request): array
    {
        $project = $this->projects->findAccessible(CurrentUser::from($request), (string) $request->route('ref'));
        if ($project === null) {
            throw HttpException::notFound('Projet introuvable.');
        }

        return $project;
    }
}
