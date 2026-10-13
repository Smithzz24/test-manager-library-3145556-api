<?php

namespace App\Domain;

use DateTimeImmutable;

final class LoanRules
{
    /** @param list<string> $bookIds */
    public static function eligible(array $bookIds, int $unreturned, bool $hasOverdue): void
    {
        if (count($bookIds) < 1 || count($bookIds) > 3) {
            throw new RuleViolation('VALIDATION_ERROR', 'El préstamo debe tener entre uno y tres libros.');
        }
        if (count(array_unique($bookIds)) !== count($bookIds)) {
            throw new RuleViolation('DUPLICATE_BOOK_IN_LOAN', 'Un libro no puede repetirse en el préstamo.');
        }
        if ($hasOverdue) {
            throw new RuleViolation('MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos.');
        }
        if ($unreturned + count($bookIds) > 3) {
            throw new RuleViolation('MEMBER_LIMIT_EXCEEDED', "El socio tiene {$unreturned} libros sin devolver; el máximo es tres.");
        }
    }

    public static function dueAt(DateTimeImmutable $loanedAt): DateTimeImmutable
    {
        return $loanedAt->modify('+14 days');
    }

    /** @param list<array{due_at: string, returned_at: ?string}> $items */
    public static function status(array $items, DateTimeImmutable $now): string
    {
        $open = false;
        foreach ($items as $item) {
            if ($item['returned_at'] === null) {
                $open = true;
                if (new DateTimeImmutable($item['due_at']) < $now) {
                    return 'overdue';
                }
            }
        }

        return $open ? 'open' : 'closed';
    }
}
