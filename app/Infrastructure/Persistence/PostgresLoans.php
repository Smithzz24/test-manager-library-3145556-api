<?php

namespace App\Infrastructure\Persistence;

use App\Application\Ports\Loans;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use LogicException;

final class PostgresLoans implements Loans
{
    public function synchronizeMember(string $memberId): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Member synchronization requires a transaction.');
        }
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$memberId]);
    }

    public function eligibility(string $memberId, DateTimeImmutable $now): array
    {
        $builder = DB::table('library.loan_item as i')->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.member_id', $memberId)->whereNull('i.returned_at');

        return ['count' => (clone $builder)->count(), 'overdue' => $builder->where('i.due_at', '<', $now)->exists()];
    }

    public function hasOpenBook(string $bookId): bool
    {
        return DB::table('library.loan_item')->where('book_id', $bookId)->whereNull('returned_at')->exists();
    }

    public function add(string $id, string $memberId, string $operatorId, DateTimeImmutable $at, array $items): void
    {
        DB::table('library.loan')->insert([
            'id' => $id, 'member_id' => $memberId, 'loaned_by_user_id' => $operatorId,
            'loaned_at' => $at->format('Y-m-d H:i:s.uP'),
        ]);
        foreach ($items as $item) {
            DB::table('library.loan_item')->insert(['loan_id' => $id, ...$item]);
        }
    }

    public function find(string $id): ?array
    {
        $row = DB::table('library.loan')->where('id', $id)->first();
        if ($row === null) {
            return null;
        }
        $items = DB::table('library.loan_item')->where('loan_id', $id)->orderBy('id')
            ->get()->map(fn (object $item): array => $this->mapItem($item))->all();

        return ['id' => $row->id, 'member_id' => $row->member_id, 'loaned_at' => $this->instant($row->loaned_at), 'items' => $items];
    }

    public function item(string $loanId, string $itemId): ?array
    {
        $row = DB::table('library.loan_item')->where('loan_id', $loanId)->where('id', $itemId)->first();

        return $row === null ? null : $this->mapItem($row);
    }

    public function markReturned(string $loanId, string $itemId, DateTimeImmutable $at): bool
    {
        return DB::table('library.loan_item')->where('loan_id', $loanId)->where('id', $itemId)->whereNull('returned_at')
            ->update(['returned_at' => $at->format('Y-m-d H:i:s.uP')]) === 1;
    }

    public function inRange(string $from, string $to): array
    {
        $rows = DB::select('SELECT i.book_id, i.book_title, i.category_name, COUNT(*) AS times_borrowed
            FROM library.loan_item i JOIN library.loan l ON l.id = i.loan_id
            WHERE l.loaned_at >= ?::date::timestamp AT TIME ZONE \'UTC\'
            AND l.loaned_at < (?::date + 1)::timestamp AT TIME ZONE \'UTC\'
            GROUP BY i.book_id, i.book_title, i.category_name
            ORDER BY times_borrowed DESC, i.book_title, i.book_id, i.category_name', [$from, $to]);

        return array_map(fn (object $row): array => [
            'book_id' => $row->book_id, 'book_title' => $row->book_title,
            'category_name' => $row->category_name, 'times_borrowed' => (int) $row->times_borrowed,
        ], $rows);
    }

    /** @return array<string, mixed> */
    private function mapItem(object $row): array
    {
        return [
            'id' => $row->id, 'book_id' => $row->book_id, 'book_title' => $row->book_title,
            'isbn' => $row->isbn, 'category_name' => $row->category_name, 'due_at' => $this->instant($row->due_at),
            'returned_at' => $row->returned_at === null ? null : $this->instant($row->returned_at),
        ];
    }

    private function instant(string $value): string
    {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
