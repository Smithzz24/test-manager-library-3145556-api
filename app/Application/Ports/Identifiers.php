<?php

namespace App\Application\Ports;

interface Identifiers
{
    public function next(): string;
}
