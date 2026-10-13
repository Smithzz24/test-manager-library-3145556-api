<?php

namespace App\Application;

use App\Application\Ports\Loans;
use App\Domain\RuleViolation;

final readonly class MostBorrowed
{
    public function __construct(private Loans $loans) {}

    /** @return array{from: string, to: string, rows: list<array<string, mixed>>} */
    public function execute(string $from, string $to): array
    {
        if ($to < $from) {
            throw new RuleViolation('INVALID_DATE_RANGE', 'La fecha final no puede ser anterior a la inicial.');
        }

        return ['from' => $from, 'to' => $to, 'rows' => $this->loans->inRange($from, $to)];
    }
}
