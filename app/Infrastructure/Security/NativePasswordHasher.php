<?php

namespace App\Infrastructure\Security;

use App\Application\Ports\PasswordHasher;

final class NativePasswordHasher implements PasswordHasher
{
    public function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public function verify(#[\SensitiveParameter] string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
