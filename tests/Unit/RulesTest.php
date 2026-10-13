<?php

namespace Tests\Unit;

use App\Application\Ports\Clock;
use App\Domain\Book;
use App\Domain\LoanRules;
use App\Domain\Operator;
use App\Domain\RuleViolation;
use App\Infrastructure\Security\JwtTokens;
use App\Infrastructure\Security\NativePasswordHasher;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RulesTest extends TestCase
{
    public function test_books_keep_counts_and_isbn_when_revised(): void
    {
        $book = new Book('id', ' Title ', 'Author', '9780307474728', 'category', 'Novela', 3, 2);
        $this->assertSame('Title', $book->title);
        $changed = $book->revise('New', 'Author', 'category', 'Novela', 4);
        $this->assertSame(3, $changed->availableCopies);
        $this->assertSame($book->isbn, $changed->isbn);
        $this->assertSame(1, $book->lend()->availableCopies);
        $this->assertSame(3, $book->returned()->availableCopies);
        $this->expectException(RuleViolation::class);
        $book->revise('New', 'Author', 'category', 'Novela', 0);
    }

    public function test_cannot_lend_the_last_unavailable_book_or_withdraw_open_book(): void
    {
        $book = new Book('id', 'Title', 'Author', '9780307474728', 'category', 'Novela', 1, 0);
        try {
            $book->lend();
            $this->fail('Unavailable book must fail.');
        } catch (RuleViolation $error) {
            $this->assertSame('BOOK_NOT_AVAILABLE', $error->errorCode);
        }
        $this->expectException(RuleViolation::class);
        $book->withdraw(new DateTimeImmutable('2026-10-13T12:00:00Z'), true);
    }

    public function test_loan_limits_duplicates_and_overdue_rules(): void
    {
        foreach ([
            [['a', 'a'], 0, false, 'DUPLICATE_BOOK_IN_LOAN'],
            [['a'], 3, false, 'MEMBER_LIMIT_EXCEEDED'],
            [['a'], 0, true, 'MEMBER_HAS_OVERDUE'],
            [[], 0, false, 'VALIDATION_ERROR'],
            [['a', 'b', 'c', 'd'], 0, false, 'VALIDATION_ERROR'],
        ] as [$ids, $count, $overdue, $code]) {
            try {
                LoanRules::eligible($ids, $count, $overdue);
                $this->fail('Invalid eligibility must fail.');
            } catch (RuleViolation $error) {
                $this->assertSame($code, $error->errorCode);
            }
        }
        LoanRules::eligible(['a', 'b'], 1, false);
    }

    public function test_status_and_due_dates_are_derived(): void
    {
        $now = new DateTimeImmutable('2026-10-13T12:00:00Z');
        $this->assertSame('2026-10-27', LoanRules::dueAt($now)->format('Y-m-d'));
        $this->assertSame('open', LoanRules::status([['due_at' => $now->format(DATE_ATOM), 'returned_at' => null]], $now));
        $this->assertSame('overdue', LoanRules::status([['due_at' => '2026-10-12T12:00:00Z', 'returned_at' => null]], $now));
        $this->assertSame('closed', LoanRules::status([['due_at' => '2026-10-12T12:00:00Z', 'returned_at' => $now->format(DATE_ATOM)]], $now));
    }

    public function test_passwords_and_jwt_signature_and_expiry(): void
    {
        $hashes = new NativePasswordHasher;
        $hash = $hashes->hash('test-password');
        $this->assertNotSame('test-password', $hash);
        $this->assertTrue($hashes->verify('test-password', $hash));
        $this->assertFalse($hashes->verify('wrong', $hash));
        $clock = new class implements Clock
        {
            public DateTimeImmutable $time;

            public function now(): DateTimeImmutable
            {
                return $this->time;
            }
        };
        $clock->time = new DateTimeImmutable('2026-10-13T12:00:00Z');
        $tokens = new JwtTokens($clock, str_repeat('a', 32));
        $user = new Operator('30000000-0000-4000-8000-000000000001', ' Ana ', $hash, 'librarian');
        $token = $tokens->issue($user);
        $this->assertSame('ana', $user->username);
        $this->assertSame(['sub' => $user->id, 'role' => 'librarian'], $tokens->verify($token));
        try {
            (new JwtTokens($clock, str_repeat('b', 32)))->verify($token);
            $this->fail('Wrong signature must fail.');
        } catch (RuleViolation $error) {
            $this->assertSame('UNAUTHENTICATED', $error->errorCode);
        }
        $clock->time = $clock->time->modify('+8 hours');
        $this->expectException(RuleViolation::class);
        $tokens->verify($token);
    }
}
