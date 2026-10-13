<?php

namespace App\Application;

use App\Application\Ports\Catalog;
use App\Application\Ports\Clock;
use App\Application\Ports\Loans;
use App\Application\Ports\Transactions;
use App\Domain\RuleViolation;

final readonly class ReturnItem
{
    public function __construct(private Catalog $catalog, private Loans $loans, private Clock $clock, private Transactions $transactions) {}

    /** @return array<string, mixed> */
    public function execute(string $loanId, string $itemId): array
    {
        return $this->transactions->run(function () use ($loanId, $itemId): array {
            $loan = $this->loans->find($loanId) ?? throw new RuleViolation('NOT_FOUND', 'El préstamo no existe.');
            $this->loans->synchronizeMember($loan['member_id']);
            $item = $this->loans->item($loanId, $itemId) ?? throw new RuleViolation('NOT_FOUND', 'El elemento no existe.');
            if ($item['returned_at'] !== null) {
                throw new RuleViolation('ITEM_ALREADY_RETURNED', 'Este libro ya fue devuelto.');
            }
            $snapshot = $this->catalog->find($item['book_id']) ?? throw new RuleViolation('NOT_FOUND', 'El libro no existe.');
            $now = $this->clock->now();
            if (! $this->loans->markReturned($loanId, $itemId, $now)) {
                throw new RuleViolation('ITEM_ALREADY_RETURNED', 'Este libro ya fue devuelto.');
            }
            if (! $this->catalog->save($snapshot->book->returned(), $snapshot->version)) {
                throw new RuleViolation('BOOK_NOT_AVAILABLE', 'Las existencias cambiaron; vuelve a intentarlo.');
            }
            $item['returned_at'] = $now->format('Y-m-d\TH:i:s\Z');

            return $item;
        });
    }
}
