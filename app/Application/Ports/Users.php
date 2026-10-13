<?php

namespace App\Application\Ports;

use App\Domain\Operator;

interface Users
{
    public function byUsername(string $username): ?Operator;

    public function byId(string $id): ?Operator;

    public function add(Operator $user): void;
}
