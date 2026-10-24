<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Controllers\Web\CurrentUser;
use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Services\InvitationService;

/**
 * Invitations des proches (superadmin).
 */
final class InvitationController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly InvitationService $invitations,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->responder->view($request, 'admin/invitations', ['invitations' => $this->invitations->latest()]);
    }

    public function store(Request $request): Response
    {
        $actor = CurrentUser::from($request);
        $data = Validator::validate($request->all(), [
            'email' => 'required|email|max:254',
            'message' => 'nullable|string|max:500',
        ], ['email' => 'adresse e-mail', 'message' => 'message personnel']);
        $this->invitations->invite($actor, (string) $data['email'], is_string($data['message'] ?? null) ? $data['message'] : null, $request->ip());

        return $this->responder->flash('success', 'Invitation envoyée à ' . $data['email'] . '.')->redirect('/admin/invitations');
    }

    public function resend(Request $request): Response
    {
        if (!$this->invitations->resend(CurrentUser::from($request), (int) $request->route('id'), $request->ip())) {
            throw HttpException::notFound();
        }

        return $this->responder->flash('success', 'Invitation renvoyée avec un nouveau lien (valable 7 jours).')->redirect('/admin/invitations');
    }

    public function revoke(Request $request): Response
    {
        if (!$this->invitations->revoke(CurrentUser::from($request)['id'], (int) $request->route('id'), $request->ip())) {
            throw HttpException::notFound();
        }

        return $this->responder->flash('success', 'Invitation révoquée.')->redirect('/admin/invitations');
    }
}
