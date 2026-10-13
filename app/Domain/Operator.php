<?php

namespace App\Domain;

final readonly class Operator
{
    public string $username;

    public function __construct(
        public string $id,
        string $username,
        public string $passwordHash,
        public string $role,
    ) {
        $this->username = strtolower(trim($username));
        if ($this->username === '' || ! in_array($role, ['admin', 'librarian'], true)) {
            throw new RuleViolation('VALIDATION_ERROR', 'El usuario o el rol no son válidos.');
        }
    }
}
