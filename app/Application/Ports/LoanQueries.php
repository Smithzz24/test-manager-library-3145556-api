<?php

namespace App\Application\Ports;

use DateTimeImmutable;

interface LoanQueries
{
    /** @return array{items: list<array<string, mixed>>, page: int, size: int, total: int} */
    public function search(?string $memberId, ?string $status, int $page, int $size, DateTimeImmutable $now): array;
}
