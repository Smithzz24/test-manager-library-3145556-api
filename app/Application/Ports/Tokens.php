<?php

namespace App\Application\Ports;

use App\Domain\Operator;

interface Tokens
{
    public function issue(Operator $user): string;

    /** @return array{sub: string, role: string} */
    public function verify(#[\SensitiveParameter] string $token): array;
}
