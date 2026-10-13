<?php

namespace App\Application\Ports;

interface Transactions
{
    /** @template T @param callable(): T $operation @return T */
    public function run(callable $operation): mixed;
}
