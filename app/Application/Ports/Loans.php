<?php

namespace App\Application\Ports;

use DateTimeImmutable;

interface Loans
{
    public function synchronizeMember(string $memberId): void;

    /** @return array{count: int, overdue: bool} */
    public function eligibility(string $memberId, DateTimeImmutable $now): array;

    public function hasOpenBook(string $bookId): bool;

    /** @param list<array<string, mixed>> $items */
    public function add(string $id, string $memberId, string $operatorId, DateTimeImmutable $at, array $items): void;

    /** @return ?array<string, mixed> */
    public function find(string $id): ?array;

    /** @return ?array<string, mixed> */
    public function item(string $loanId, string $itemId): ?array;

    public function markReturned(string $loanId, string $itemId, DateTimeImmutable $at): bool;

    /** @return list<array<string, mixed>> */
    public function inRange(string $from, string $to): array;
}
