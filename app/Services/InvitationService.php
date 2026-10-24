<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database\Database;
use App\Core\Http\UrlGenerator;
use App\Core\Security\PasswordHasher;
use App\Core\Security\PasswordPolicy;
use App\Core\Validation\ValidationException;
use App\Repositories\InvitationRepository;
use App\Repositories\PlanRepository;
use App\Repositories\UserRepository;
use App\Services\Mail\PlatformMailer;

/**
 * Invitations de proches : un compte « pending » est créé, il ne devient utilisable qu'après
 * acceptation via le lien reçu par e-mail (valable 7 jours, usage unique).
 *
 * @phpstan-import-type InvitationRow from InvitationRepository
 */
final class InvitationService
{
    private const int TTL = 604800;

    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly UserRepository $users,
        private readonly PlanRepository $plans,
        private readonly PasswordHasher $hasher,
        private readonly PasswordPolicy $policy,
        private readonly PlatformMailer $mailer,
        private readonly UrlGenerator $urls,
        private readonly AuditService $audit,
        private readonly LoginEventService $loginEvents,
        private readonly Database $db,
    ) {
    }

    /**
     * @param array{id: int, name: string} $inviter
     *
     * @throws ValidationException adresse déjà inscrite ou déjà invitée
     */
    public function invite(array $inviter, string $email, ?string $message, string $ip): void
    {
        if ($this->users->emailExists($email)) {
            throw ValidationException::withMessage('email', 'Un compte existe déjà avec cette adresse.', ['email' => $email, 'message' => (string) $message]);
        }
        if ($this->invitations->hasPending($email)) {
            throw ValidationException::withMessage('email', 'Une invitation est déjà en attente pour cette adresse : utilisez « Renvoyer ».', ['email' => $email]);
        }
        $token = bin2hex(random_bytes(32));
        $id = $this->db->transaction(function () use ($email, $inviter, $token, $message): int {
            $userId = $this->users->createPending($email, $this->plans->defaultPlanId());

            return $this->invitations->create($email, $userId, $inviter['id'], hash('sha256', $token), $message, self::TTL);
        });
        $this->sendEmail($email, $inviter['name'], $message, $token);
        $this->audit->record('invitation.sent', $inviter['id'], 'invitation:' . $id, $ip, ['email' => $email]);
    }

    /**
     * Nouveau lien (l'ancien devient invalide).
     *
     * @param array{id: int, name: string} $inviter
     */
    public function resend(array $inviter, int $invitationId, string $ip): bool
    {
        $invitation = $this->invitations->find($invitationId);
        if ($invitation === null || !in_array($invitation['status'], ['pending', 'expired'], true)) {
            return false;
        }
        $token = bin2hex(random_bytes(32));
        $this->invitations->renewToken($invitationId, hash('sha256', $token), self::TTL);
        $this->sendEmail($invitation['email'], $inviter['name'], $invitation['message'], $token);
        $this->audit->record('invitation.resent', $inviter['id'], 'invitation:' . $invitationId, $ip);

        return true;
    }

    /**
     * Révoque l'invitation et supprime le compte en attente associé.
     */
    public function revoke(int $actorId, int $invitationId, string $ip): bool
    {
        $invitation = $this->invitations->find($invitationId);
        if ($invitation === null || !in_array($invitation['status'], ['pending', 'expired'], true)) {
            return false;
        }
        $this->db->transaction(function () use ($invitation): void {
            $this->invitations->markRevoked($invitation['id']);
            $user = $invitation['user_id'] !== null ? $this->users->findById($invitation['user_id']) : null;
            if ($user !== null && $user['status'] === 'pending') {
                $this->users->delete($user['id']);
            }
        });
        $this->audit->record('invitation.revoked', $actorId, 'invitation:' . $invitationId, $ip);

        return true;
    }

    /**
     * @return InvitationRow|null
     */
    public function findPending(string $token): ?array
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1 ? $this->invitations->findPendingByHash(hash('sha256', $token)) : null;
    }

    /**
     * Acceptation : définit nom et mot de passe, active le compte (adresse vérifiée).
     *
     * @return int|null identifiant du compte activé, null si l'invitation n'est plus valable
     *
     * @throws ValidationException mot de passe refusé
     */
    public function accept(string $token, string $name, string $password, string $ip): ?int
    {
        $invitation = $this->findPending($token);
        if ($invitation === null || $invitation['user_id'] === null) {
            return null;
        }
        $errors = $this->policy->validate($password, $invitation['email'], $name);
        if ($errors !== []) {
            throw new ValidationException(['password' => $errors], ['name' => $name]);
        }
        $userId = $invitation['user_id'];
        $accepted = $this->db->transaction(function () use ($invitation, $userId, $name, $password): bool {
            if (!$this->invitations->markAccepted($invitation['id'])) {
                return false;
            }
            $this->users->activateInvited($userId, $name, $this->hasher->hash($password));

            return true;
        });
        if (!$accepted) {
            return null;
        }
        $this->audit->record('invitation.accepted', $userId, 'invitation:' . $invitation['id'], $ip);
        $this->loginEvents->record('invitation_accepted', $userId, $invitation['email'], $ip);

        return $userId;
    }

    /** @return list<InvitationRow> */
    public function latest(): array
    {
        return $this->invitations->latest();
    }

    private function sendEmail(string $email, string $inviterName, ?string $message, string $token): void
    {
        $this->mailer->send($email, $inviterName . ' vous invite sur NovaBase', 'invitation', [
            'inviterName' => $inviterName,
            'personalMessage' => $message,
            'url' => $this->urls->absolute('/invitation/' . $token),
        ]);
    }
}
