<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Controllers\Web\CurrentUser;
use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Validation\ValidationException;
use App\Core\Validation\Validator;
use App\Repositories\TestimonialRepository;
use App\Services\AuditService;
use App\Services\AvatarService;
use App\Services\CommunityService;

/**
 * Gestion des témoignages affichés sur la page d'accueil.
 */
final class TestimonialController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly TestimonialRepository $testimonials,
        private readonly AvatarService $avatars,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->responder->view($request, 'admin/testimonials/index', ['testimonials' => $this->testimonials->all()]);
    }

    public function create(Request $request): Response
    {
        return $this->responder->view($request, 'admin/testimonials/form', ['testimonial' => null]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request, null);
        $id = $this->testimonials->create($data);
        $this->audit->record('admin.testimonial_created', CurrentUser::from($request)['id'], 'testimonial:' . $id, $request->ip());

        return $this->responder->flash('success', 'Témoignage ajouté.')->redirect('/admin/testimonials');
    }

    public function edit(Request $request): Response
    {
        return $this->responder->view($request, 'admin/testimonials/form', ['testimonial' => $this->findOrFail($request)]);
    }

    public function update(Request $request): Response
    {
        $existing = $this->findOrFail($request);
        $data = $this->validated($request, $existing['avatar_file']);
        if ($existing['avatar_file'] !== null && $existing['avatar_file'] !== $data['avatar_file']) {
            $this->avatars->delete($existing['avatar_file']);
        }
        $this->testimonials->update($existing['id'], $data);
        $this->audit->record('admin.testimonial_updated', CurrentUser::from($request)['id'], 'testimonial:' . $existing['id'], $request->ip());

        return $this->responder->flash('success', 'Témoignage mis à jour.')->redirect('/admin/testimonials');
    }

    public function togglePublish(Request $request): Response
    {
        $testimonial = $this->findOrFail($request);
        $this->testimonials->setPublished($testimonial['id'], !$testimonial['is_published']);
        $this->audit->record($testimonial['is_published'] ? 'admin.testimonial_unpublished' : 'admin.testimonial_published', CurrentUser::from($request)['id'], 'testimonial:' . $testimonial['id'], $request->ip());

        return $this->responder->flash('success', $testimonial['is_published'] ? 'Témoignage retiré de la page d\'accueil.' : 'Témoignage publié sur la page d\'accueil.')->redirect('/admin/testimonials');
    }

    public function delete(Request $request): Response
    {
        $testimonial = $this->findOrFail($request);
        $this->testimonials->delete($testimonial['id']);
        $this->avatars->delete($testimonial['avatar_file']);
        $this->audit->record('admin.testimonial_deleted', CurrentUser::from($request)['id'], 'testimonial:' . $testimonial['id'], $request->ip());

        return $this->responder->flash('success', 'Témoignage supprimé.')->redirect('/admin/testimonials');
    }

    /**
     * @return array{id: int, author_name: string, handle: string, platform: string, profile_url: string|null, avatar_file: string|null, content: string, is_published: bool, sort: int}
     */
    private function findOrFail(Request $request): array
    {
        $testimonial = $this->testimonials->find((int) $request->route('id'));
        if ($testimonial === null) {
            throw HttpException::notFound();
        }

        return $testimonial;
    }

    /**
     * @return array{author_name: string, handle: string, platform: string, profile_url: string|null, avatar_file: string|null, content: string, is_published: bool, sort: int}
     */
    private function validated(Request $request, ?string $currentAvatar): array
    {
        $data = Validator::validate($request->all(), [
            'author_name' => 'required|string|max:120',
            'handle' => ['required', 'string', 'max:60', 'regex:/^@?[\pL\pN_.\-]{1,59}$/u'],
            'platform' => 'required|in:' . implode(',', array_keys(CommunityService::PLATFORMS)),
            'profile_url' => 'nullable|url|max:500',
            'content' => 'required|string|min:10|max:600',
            'sort' => 'nullable|integer|between:0,9999',
            'is_published' => 'boolean',
            'remove_avatar' => 'boolean',
        ], [
            'author_name' => 'nom',
            'handle' => 'identifiant',
            'platform' => 'réseau',
            'profile_url' => 'lien du profil',
            'content' => 'témoignage',
            'sort' => 'ordre',
        ]);

        $avatar = $data['remove_avatar'] === true ? null : $currentAvatar;
        $file = $request->files()['avatar'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $avatar = $this->avatars->store($file);
            } catch (ValidationException $e) {
                // Image refusée : on réaffiche le formulaire avec la saisie conservée.
                throw new ValidationException($e->errors(), array_filter($data, 'is_scalar'));
            }
        }
        $handle = (string) $data['handle'];

        return [
            'author_name' => (string) $data['author_name'],
            'handle' => str_starts_with($handle, '@') ? $handle : '@' . $handle,
            'platform' => (string) $data['platform'],
            'profile_url' => is_string($data['profile_url'] ?? null) ? $data['profile_url'] : null,
            'avatar_file' => $avatar,
            'content' => (string) $data['content'],
            'is_published' => $data['is_published'] === true,
            'sort' => is_int($data['sort'] ?? null) ? $data['sort'] : 0,
        ];
    }
}
