<?php

namespace App\Infrastructure\Persistence;

use App\Application\Ports\Transactions;
use Illuminate\Support\Facades\DB;

final class PostgresTransactions implements Transactions
{
    public function run(callable $operation): mixed
    {
        return DB::transaction($operation);
    }
}
