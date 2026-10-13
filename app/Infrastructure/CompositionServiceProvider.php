<?php

namespace App\Infrastructure;

use App\Application\Ports\Catalog;
use App\Application\Ports\Clock;
use App\Application\Ports\Identifiers;
use App\Application\Ports\LoanQueries;
use App\Application\Ports\Loans;
use App\Application\Ports\Members;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\Tokens;
use App\Application\Ports\Transactions;
use App\Application\Ports\Users;
use App\Infrastructure\Persistence\PostgresCatalog;
use App\Infrastructure\Persistence\PostgresLoanQueries;
use App\Infrastructure\Persistence\PostgresLoans;
use App\Infrastructure\Persistence\PostgresMembers;
use App\Infrastructure\Persistence\PostgresTransactions;
use App\Infrastructure\Persistence\PostgresUsers;
use App\Infrastructure\Security\JwtTokens;
use App\Infrastructure\Security\NativePasswordHasher;
use Illuminate\Support\ServiceProvider;

final class CompositionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Catalog::class, PostgresCatalog::class);
        $this->app->bind(Members::class, PostgresMembers::class);
        $this->app->bind(Users::class, PostgresUsers::class);
        $this->app->bind(Loans::class, PostgresLoans::class);
        $this->app->bind(LoanQueries::class, PostgresLoanQueries::class);
        $this->app->bind(Transactions::class, PostgresTransactions::class);
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(Identifiers::class, UuidIdentifiers::class);
        $this->app->bind(PasswordHasher::class, NativePasswordHasher::class);
        $this->app->bind(Tokens::class, fn () => new JwtTokens($this->app->make(Clock::class), (string) config('library.jwt_secret')));
    }
}
