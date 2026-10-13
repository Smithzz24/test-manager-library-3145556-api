<?php

namespace App\Application;

use App\Application\Ports\Catalog;
use App\Application\Ports\Clock;
use App\Application\Ports\Loans;
use App\Application\Ports\Transactions;
use App\Domain\RuleViolation;

final readonly class WithdrawBook
{
    public function __construct(private Catalog $catalog, private Loans $loans, private Clock $clock, private Transactions $transactions) {}

    public function execute(string $id): void
    {
        $this->transactions->run(function () use ($id): void {
            $snapshot = $this->catalog->find($id) ?? throw new RuleViolation('NOT_FOUND', 'El libro no existe.');
            $book = $snapshot->book->withdraw($this->clock->now(), $this->loans->hasOpenBook($id));
            if (! $this->catalog->save($book, $snapshot->version)) {
                throw new RuleViolation('BOOK_NOT_AVAILABLE', 'El libro cambió durante la operación; vuelve a intentarlo.');
            }
        });
    }
}
