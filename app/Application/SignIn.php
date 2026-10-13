<?php

namespace App\Application;

use App\Application\Ports\PasswordHasher;
use App\Application\Ports\Tokens;
use App\Application\Ports\Users;
use App\Domain\RuleViolation;

final readonly class SignIn
{
    public function __construct(private Users $users, private PasswordHasher $hashes, private Tokens $tokens) {}

    /** @return array{access_token: string, token_type: string, expires_in: int, role: string} */
    public function execute(string $username, #[\SensitiveParameter] string $password): array
    {
        $user = $this->users->byUsername(strtolower(trim($username)));
        if ($user === null || ! $this->hashes->verify($password, $user->passwordHash)) {
            throw new RuleViolation('INVALID_CREDENTIALS', 'El usuario o la contraseña son incorrectos.');
        }

        return ['access_token' => $this->tokens->issue($user), 'token_type' => 'Bearer', 'expires_in' => 28800, 'role' => $user->role];
    }
}
