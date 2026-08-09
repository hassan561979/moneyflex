<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Resolves the user behind an HTTP Basic Authorization header. Shared by the
 * Basic only middleware and the combined Basic or JWT middleware, so the
 * credential check exists in exactly one place.
 */
final class BasicCredentials
{
    /**
     * A valid bcrypt digest that no password matches. Verifying against it
     * keeps the "no such account" path as slow as the "wrong password" path,
     * so response timing does not disclose which accounts exist.
     */
    private const DUMMY_HASH = '$2y$12$usesomesillystringfore.Yr9GkvXlPZ7yGZWhF8fRnGoOhOmYzS';

    public function resolve(Request $request): ?User
    {
        $email = $request->getUser();
        $password = $request->getPassword();

        if (blank($email) || blank($password)) {
            return null;
        }

        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        if ($user === null) {
            Hash::check($password, self::DUMMY_HASH);

            return null;
        }

        return Hash::check($password, $user->password) ? $user : null;
    }
}
