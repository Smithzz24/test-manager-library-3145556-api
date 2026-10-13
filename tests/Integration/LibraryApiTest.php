<?php

namespace Tests\Integration;

use App\Application\Ports\Clock;
use App\Application\Ports\Tokens;
use App\Application\Ports\Users;
use DateTimeImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class LibraryApiTest extends TestCase
{
    private string $admin;

    private string $librarian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('library_test', DB::selectOne('SELECT current_database() AS name')->name);
        $this->assertSame('yes', getenv('LIBRARY_TEST_DESTRUCTIVE'));
        $this->assertSame(0, Artisan::call('migrate:reset', ['--force' => true]));
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        config(['library.jwt_secret' => str_repeat('test-key', 8), 'library.admin_username' => 'admin', 'library.admin_password' => 'test-password']);
        $this->assertSame(0, Artisan::call('library:provision-admin'));
        $this->admin = $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'test-password'])
            ->assertOk()->assertJsonPath('expires_in', 28800)->json('access_token');
        $this->withToken($this->admin)->postJson('/api/v1/users', ['username' => ' Librarian ', 'password' => 'test-password', 'role' => 'librarian'])
            ->assertCreated()->assertJsonPath('username', 'librarian')->assertJsonMissingPath('password_hash');
        $this->librarian = $this->postJson('/api/v1/auth/login', ['username' => 'librarian', 'password' => 'test-password'])
            ->assertOk()->json('access_token');
        $this->withToken($this->admin);
    }

    public function test_authentication_permissions_errors_and_admin_provisioning(): void
    {
        $this->withHeaders(['Authorization' => ''])->getJson('/api/v1/books')->assertUnauthorized()
            ->assertExactJson(['code' => 'UNAUTHENTICATED', 'message' => 'Debes iniciar sesión con un token válido.', 'details' => []]);
        $this->withToken('invalid')->getJson('/api/v1/books')->assertUnauthorized();
        $first = $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'wrong'])->assertUnauthorized()->json();
        $second = $this->postJson('/api/v1/auth/login', ['username' => 'missing', 'password' => 'wrong'])->assertUnauthorized()->json();
        $this->assertSame($first, $second);
        $this->withToken($this->librarian)->postJson('/api/v1/books', [])->assertForbidden()->assertJsonPath('code', 'FORBIDDEN_ROLE');
        $this->getJson('/api/v1/reports/most-borrowed')->assertForbidden();
        $this->withToken($this->admin)->postJson('/api/v1/users', ['username' => 'other', 'password' => 'pw', 'role' => 'admin'])->assertUnprocessable();
        $this->postJson('/api/v1/users', ['username' => ' LIBRARIAN ', 'password' => 'pw', 'role' => 'librarian'])
            ->assertConflict()->assertJsonPath('code', 'USERNAME_TAKEN');
        $this->assertSame(0, Artisan::call('library:provision-admin'));
        $this->assertSame(2, DB::table('library.user')->count());
    }

    public function test_catalog_search_pagination_validation_and_isbn_uniqueness(): void
    {
        $book = $this->book();
        $this->book('9780307474729', 'Other title');
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(5);
        $this->getJson('/api/v1/books?q=Original&size=1')->assertOk()->assertJsonPath('total', 1)->assertJsonCount(1, 'items');
        $this->getJson('/api/v1/books?page=2&size=1')->assertOk()->assertJsonPath('total', 2)->assertJsonCount(1, 'items');
        foreach (['size=51', 'size=0', 'page=-1', 'page=x'] as $query) {
            $this->getJson('/api/v1/books?'.$query)->assertUnprocessable()->assertJsonStructure(['code', 'message', 'details']);
        }
        $this->postJson('/api/v1/books', $this->bookInput())->assertConflict()->assertJsonPath('code', 'ISBN_TAKEN');
        $this->putJson('/api/v1/books/'.$book['id'], [...$this->bookInput(), 'isbn' => '9780307474730'])->assertUnprocessable();
        $this->postJson('/api/v1/books', [...$this->bookInput(), 'isbn' => 'bad'])->assertUnprocessable();
    }

    public function test_member_document_uniqueness_search_and_no_delete_operation(): void
    {
        $member = $this->member();
        $this->postJson('/api/v1/members', ['full_name' => 'Other', 'document_number' => 'DOC-1'])
            ->assertConflict()->assertJsonPath('code', 'DOCUMENT_TAKEN');
        $this->getJson('/api/v1/members?q=Member')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/members/'.$member['id'])->assertOk()->assertJsonPath('active_loans', 0);
        $this->deleteJson('/api/v1/members/'.$member['id'])->assertStatus(405);
    }

    public function test_loan_return_history_and_soft_delete_end_to_end(): void
    {
        $book = $this->book();
        $member = $this->member();
        $loan = $this->postJson('/api/v1/loans', ['member_id' => $member['id'], 'items' => [['book_id' => $book['id']]]])
            ->assertCreated()->assertJsonPath('status', 'open')->json();
        $this->assertSame(0, DB::table('library.book')->value('available_copies'));
        $this->getJson('/api/v1/members/'.$member['id'])->assertJsonPath('active_loans', 1);
        $this->deleteJson('/api/v1/books/'.$book['id'])->assertConflict()->assertJsonPath('code', 'BOOK_HAS_OPEN_LOANS');
        $this->putJson('/api/v1/books/'.$book['id'], ['title' => 'Renamed', 'author' => 'Author',
            'category_id' => '22222222-2222-4222-8222-222222222222', 'total_copies' => 1])->assertOk();
        $date = substr($loan['loaned_at'], 0, 10);
        $this->getJson("/api/v1/reports/most-borrowed?from={$date}&to={$date}")->assertOk()
            ->assertJsonPath('rows.0.book_title', 'Original title')->assertJsonPath('rows.0.category_name', 'Novela')
            ->assertJsonPath('rows.0.times_borrowed', 1);
        $path = '/api/v1/loans/'.$loan['id'].'/items/'.$loan['items'][0]['id'].'/return';
        $this->postJson($path)->assertOk()->assertJsonMissingPath('password_hash');
        $this->postJson($path)->assertConflict()->assertJsonPath('code', 'ITEM_ALREADY_RETURNED');
        $this->assertSame(1, DB::table('library.book')->value('available_copies'));
        $this->getJson('/api/v1/loans/'.$loan['id'])->assertOk()->assertJsonPath('status', 'closed');
        $this->deleteJson('/api/v1/books/'.$book['id'])->assertNoContent();
        $this->getJson('/api/v1/books/'.$book['id'])->assertNotFound();
        $this->getJson('/api/v1/books')->assertJsonPath('total', 0);
        $this->assertSame(1, DB::table('library.loan_item')->count());
        $this->book();
    }

    public function test_no_stock_rolls_back_the_entire_loan_and_duplicates_fail(): void
    {
        $book = $this->book();
        $empty = $this->book('9780307474729');
        $member = $this->member();
        DB::table('library.book')->where('id', $empty['id'])->update(['available_copies' => 0]);
        $this->postJson('/api/v1/loans', ['member_id' => $member['id'], 'items' => [['book_id' => $book['id']], ['book_id' => $empty['id']]]])
            ->assertConflict()->assertJsonPath('code', 'BOOK_NOT_AVAILABLE');
        $this->assertSame(0, DB::table('library.loan')->count());
        $this->assertSame(1, DB::table('library.book')->where('id', $book['id'])->value('available_copies'));
        $this->postJson('/api/v1/loans', ['member_id' => $member['id'], 'items' => [['book_id' => $book['id']], ['book_id' => $book['id']]]])
            ->assertUnprocessable()->assertJsonPath('code', 'DUPLICATE_BOOK_IN_LOAN');
    }

    public function test_member_global_limit_and_overdue_prevent_new_loans(): void
    {
        $member = $this->member();
        $books = [];
        foreach (['9780307474728', '9780307474729', '9780307474730', '9780307474731'] as $isbn) {
            $books[] = $this->book($isbn);
        }
        $loan = $this->postJson('/api/v1/loans', ['member_id' => $member['id'], 'items' => array_map(fn (array $b): array => ['book_id' => $b['id']], array_slice($books, 0, 3))])
            ->assertCreated()->json();
        $this->postJson('/api/v1/loans', ['member_id' => $member['id'], 'items' => [['book_id' => $books[3]['id']]]])
            ->assertUnprocessable()->assertJsonPath('code', 'MEMBER_LIMIT_EXCEEDED');
        DB::table('library.loan_item')->where('loan_id', $loan['id'])->update(['due_at' => '2000-01-01T00:00:00Z']);
        $this->postJson('/api/v1/loans', ['member_id' => $member['id'], 'items' => [['book_id' => $books[3]['id']]]])
            ->assertConflict()->assertJsonPath('code', 'MEMBER_HAS_OVERDUE');
        $this->getJson('/api/v1/loans?status=overdue')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_expired_token_is_rejected_by_the_api(): void
    {
        $user = app(Users::class)->byUsername('admin');
        $clock = new class implements Clock
        {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('-9 hours');
            }
        };
        $this->app->instance(Clock::class, $clock);
        $token = app(Tokens::class)->issue($user);
        $this->app->forgetInstance(Clock::class);
        $this->withToken($token)->getJson('/api/v1/books')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_two_independent_api_processes_lend_the_last_copy_exactly_once(): void
    {
        $book = $this->book();
        $first = $this->member();
        $second = $this->postJson('/api/v1/members', ['full_name' => 'Second', 'document_number' => 'DOC-2'])->assertCreated()->json();
        $executeAt = microtime(true) + 1;
        $workers = [];
        foreach ([$first, $second] as $member) {
            $worker = new Process([PHP_BINARY, dirname(__DIR__).'/Support/request-worker.php'],
                dirname(__DIR__, 2), ['JWT_SECRET' => config('library.jwt_secret'), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false']);
            $worker->setInput(json_encode([
                'execute_at' => $executeAt, 'path' => '/api/v1/loans', 'token' => $this->admin,
                'body' => ['member_id' => $member['id'], 'items' => [['book_id' => $book['id']]]],
            ], JSON_THROW_ON_ERROR));
            $worker->setTimeout(15);
            $worker->start();
            $workers[] = $worker;
        }
        $statuses = [];
        foreach ($workers as $worker) {
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $output = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $statuses[] = $output['status'];
            if ($output['status'] === 409) {
                $this->assertSame('BOOK_NOT_AVAILABLE', $output['body']['code']);
            }
        }
        sort($statuses);
        $this->assertSame([201, 409], $statuses);
        $this->assertSame(0, DB::table('library.book')->value('available_copies'));
        $this->assertSame(1, DB::table('library.loan')->count());
        $this->assertSame(1, DB::table('library.loan_item')->count());
    }

    public function test_two_independent_api_processes_return_one_item_exactly_once(): void
    {
        $book = $this->book();
        $member = $this->member();
        $loan = $this->postJson('/api/v1/loans', ['member_id' => $member['id'], 'items' => [['book_id' => $book['id']]]])->assertCreated()->json();
        $path = '/api/v1/loans/'.$loan['id'].'/items/'.$loan['items'][0]['id'].'/return';
        $executeAt = microtime(true) + 1;
        $workers = [];
        for ($i = 0; $i < 2; $i++) {
            $worker = new Process([PHP_BINARY, dirname(__DIR__).'/Support/request-worker.php'],
                dirname(__DIR__, 2), ['JWT_SECRET' => config('library.jwt_secret'), 'APP_ENV' => 'testing']);
            $worker->setInput(json_encode(['execute_at' => $executeAt, 'path' => $path, 'token' => $this->admin, 'body' => []], JSON_THROW_ON_ERROR));
            $worker->setTimeout(15);
            $worker->start();
            $workers[] = $worker;
        }
        $statuses = [];
        foreach ($workers as $worker) {
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $output = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $statuses[] = $output['status'];
            if ($output['status'] === 409) {
                $this->assertSame('ITEM_ALREADY_RETURNED', $output['body']['code']);
            }
        }
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $this->assertSame(1, DB::table('library.book')->value('available_copies'));
    }

    /** @return array<string, mixed> */
    private function book(string $isbn = '9780307474728', string $title = 'Original title'): array
    {
        return $this->postJson('/api/v1/books', [...$this->bookInput(), 'isbn' => $isbn, 'title' => $title])
            ->assertCreated()->json();
    }

    /** @return array<string, mixed> */
    private function bookInput(): array
    {
        return ['title' => 'Original title', 'author' => 'Author', 'isbn' => '9780307474728',
            'category_id' => '11111111-1111-4111-8111-111111111111', 'total_copies' => 1];
    }

    /** @return array<string, mixed> */
    private function member(): array
    {
        return $this->postJson('/api/v1/members', ['full_name' => 'Member', 'document_number' => 'DOC-1'])
            ->assertCreated()->json();
    }
}
