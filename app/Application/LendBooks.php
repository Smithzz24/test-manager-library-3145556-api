<?php

namespace App\Application;

use App\Application\Ports\Catalog;
use App\Application\Ports\Clock;
use App\Application\Ports\Identifiers;
use App\Application\Ports\Loans;
use App\Application\Ports\Members;
use App\Application\Ports\Transactions;
use App\Domain\LoanRules;
use App\Domain\RuleViolation;

final readonly class LendBooks
{
    public function __construct(
        private Catalog $catalog, private Members $members, private Loans $loans,
        private Clock $clock, private Identifiers $ids, private Transactions $transactions,
    ) {}

    /** @param list<string> $bookIds @return array<string, mixed> */
    public function execute(string $memberId, string $operatorId, array $bookIds): array
    {
        return $this->transactions->run(function () use ($memberId, $operatorId, $bookIds): array {
            $this->loans->synchronizeMember($memberId);
            if ($this->members->find($memberId) === null) {
                throw new RuleViolation('NOT_FOUND', 'El socio no existe.');
            }
            $now = $this->clock->now();
            $eligibility = $this->loans->eligibility($memberId, $now);
            LoanRules::eligible($bookIds, $eligibility['count'], $eligibility['overdue']);
            $items = [];
            sort($bookIds);
            foreach ($bookIds as $bookId) {
                $snapshot = $this->catalog->find($bookId) ?? throw new RuleViolation('NOT_FOUND', 'El libro no existe.');
                $book = $snapshot->book->lend();
                if (! $this->catalog->save($book, $snapshot->version)) {
                    throw new RuleViolation('BOOK_NOT_AVAILABLE', 'Los ejemplares disponibles cambiaron; vuelve a intentarlo.');
                }
                $items[] = [
                    'id' => $this->ids->next(), 'book_id' => $book->id,
                    'book_title' => $book->title, 'isbn' => $book->isbn, 'category_name' => $book->categoryName,
                    'due_at' => LoanRules::dueAt($now)->format('Y-m-d\TH:i:s\Z'), 'returned_at' => null,
                ];
            }
            $id = $this->ids->next();
            $this->loans->add($id, $memberId, $operatorId, $now, $items);

            return ['id' => $id, 'member_id' => $memberId, 'loaned_at' => $now->format('Y-m-d\TH:i:s\Z'), 'status' => 'open', 'items' => $items];
        });
    }
}
