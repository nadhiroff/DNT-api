<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Repositories\UserRepository;

/**
 * Accès typé à l'utilisateur placé dans la requête par le middleware Authenticate.
 *
 * @phpstan-import-type UserRow from UserRepository
 */
final class CurrentUser
{
    /**
     * @return UserRow
     */
    public static function from(Request $request): array
    {
        $user = $request->attribute('user');
        if (!is_array($user) || !is_int($user['id'] ?? null)) {
            throw new HttpException(401, 'unauthenticated');
        }

        /** @var UserRow $user */
        return $user;
    }
}
