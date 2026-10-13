<?php

namespace App\Infrastructure;

use App\Application\Ports\Identifiers;
use Illuminate\Support\Str;

final class UuidIdentifiers implements Identifiers
{
    public function next(): string
    {
        return (string) Str::uuid();
    }
}
