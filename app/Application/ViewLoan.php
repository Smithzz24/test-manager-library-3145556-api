<?php

namespace App\Application;

use App\Application\Ports\Clock;
use App\Application\Ports\Loans;
use App\Domain\LoanRules;
use App\Domain\RuleViolation;

final readonly class ViewLoan
{
    public function __construct(private Loans $loans, private Clock $clock) {}

    /** @return array<string, mixed> */
    public function execute(string $id): array
    {
        $loan = $this->loans->find($id) ?? throw new RuleViolation('NOT_FOUND', 'El préstamo no existe.');
        $loan['status'] = LoanRules::status($loan['items'], $this->clock->now());

        return $loan;
    }
}
