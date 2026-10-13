<?php

namespace App\Infrastructure\Persistence;

use App\Application\Ports\LoanQueries;
use App\Application\Ports\Loans;
use App\Domain\LoanRules;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class PostgresLoanQueries implements LoanQueries
{
    public function __construct(private Loans $loans) {}

    public function search(?string $memberId, ?string $status, int $page, int $size, DateTimeImmutable $now): array
    {
        $q = DB::table('library.loan as l');
        if ($memberId !== null) {
            $q->where('l.member_id', $memberId);
        }
        $open = 'EXISTS (SELECT 1 FROM library.loan_item i WHERE i.loan_id = l.id AND i.returned_at IS NULL)';
        $overdue = 'EXISTS (SELECT 1 FROM library.loan_item i WHERE i.loan_id = l.id AND i.returned_at IS NULL AND i.due_at < ?)';
        if ($status === 'closed') {
            $q->whereRaw("NOT {$open}");
        } elseif ($status === 'overdue') {
            $q->whereRaw($overdue, [$now]);
        } elseif ($status === 'open') {
            $q->whereRaw($open)->whereRaw("NOT {$overdue}", [$now]);
        }
        $total = (clone $q)->count();
        $items = [];
        foreach ($q->orderByDesc('loaned_at')->orderBy('id')->offset(($page - 1) * $size)->limit($size)->pluck('id') as $id) {
            $loan = $this->loans->find($id);
            if ($loan !== null) {
                $loan['status'] = LoanRules::status($loan['items'], $now);
                $items[] = $loan;
            }
        }

        return compact('items', 'page', 'size', 'total');
    }
}
