<?php

namespace App\Application;

use App\Application\Ports\Identifiers;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\Users;
use App\Domain\Operator;
use App\Domain\RuleViolation;

final readonly class CreateLibrarian
{
    public function __construct(private Users $users, private PasswordHasher $hashes, private Identifiers $ids) {}

    public function execute(string $username, #[\SensitiveParameter] string $password, string $role): Operator
    {
        if ($role !== 'librarian') {
            throw new RuleViolation('VALIDATION_ERROR', 'Solo se pueden crear bibliotecarios.');
        }
        $user = new Operator($this->ids->next(), $username, $this->hashes->hash($password), $role);
        $this->users->add($user);

        return $user;
    }
}
