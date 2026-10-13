<?php

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LibraryMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::selectOne('SELECT current_database() AS name')->name;
        $this->assertSame('library_test', $database, 'Only the isolated library_test database may be used.');
        $this->assertSame('yes', getenv('LIBRARY_TEST_DESTRUCTIVE'), 'Explicit destructive-test opt-in required.');
        $this->assertSame('16', explode('.', DB::selectOne('SHOW server_version')->server_version)[0]);

        $this->assertSame(0, Artisan::call('migrate:reset', ['--force' => true]));
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
    }

    protected function tearDown(): void
    {
        if ($this->app !== null && getenv('LIBRARY_TEST_DESTRUCTIVE') === 'yes'
            && config('database.connections.pgsql.database') === 'library_test') {
            $this->assertSame(0, Artisan::call('migrate:reset', ['--force' => true]));
        }

        parent::tearDown();
    }

    public function test_exact_schema_has_six_tables_and_thirty_columns_without_defaults(): void
    {
        $expected = [
            'category' => ['id' => 'uuid', 'name' => 'varchar:120'],
            'book' => [
                'id' => 'uuid', 'title' => 'varchar:250', 'author' => 'varchar:200', 'isbn' => 'bpchar:13',
                'category_id' => 'uuid', 'total_copies' => 'int4', 'available_copies' => 'int4',
                'cover_key' => 'varchar:512?', 'deleted_at' => 'timestamptz?',
            ],
            'member' => ['id' => 'uuid', 'full_name' => 'varchar:200', 'document_number' => 'varchar:30'],
            'user' => ['id' => 'uuid', 'username' => 'varchar:120', 'password_hash' => 'varchar:512', 'role' => 'varchar:40'],
            'loan' => ['id' => 'uuid', 'member_id' => 'uuid', 'loaned_by_user_id' => 'uuid', 'loaned_at' => 'timestamptz'],
            'loan_item' => [
                'id' => 'uuid', 'loan_id' => 'uuid', 'book_id' => 'uuid', 'book_title' => 'varchar:250',
                'isbn' => 'bpchar:13', 'category_name' => 'varchar:120', 'due_at' => 'timestamptz',
                'returned_at' => 'timestamptz?',
            ],
        ];
        $actual = [];
        $columns = DB::select("SELECT table_name, column_name, udt_name, character_maximum_length, is_nullable, column_default
            FROM information_schema.columns WHERE table_schema = 'library' ORDER BY table_name, ordinal_position");

        $this->assertCount(30, $columns);
        foreach ($columns as $column) {
            $this->assertNull($column->column_default);
            $actual[$column->table_name][$column->column_name] = $column->udt_name
                .($column->character_maximum_length === null ? '' : ':'.$column->character_maximum_length)
                .($column->is_nullable === 'YES' ? '?' : '');
        }
        ksort($expected);
        $this->assertSame($expected, $actual);
        $this->assertSame('UTC', DB::selectOne('SHOW timezone')->TimeZone);
    }

    public function test_categories_are_exact_and_migrating_twice_changes_nothing(): void
    {
        $expected = [
            '11111111-1111-4111-8111-111111111111' => 'Novela',
            '22222222-2222-4222-8222-222222222222' => 'Ciencia',
            '33333333-3333-4333-8333-333333333333' => 'Tecnología',
            '44444444-4444-4444-8444-444444444444' => 'Historia',
            '55555555-5555-4555-8555-555555555555' => 'Infantil',
        ];
        $this->assertSame($expected, DB::table('library.category')->orderBy('id')->pluck('name', 'id')->all());
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertSame($expected, DB::table('library.category')->orderBy('id')->pluck('name', 'id')->all());
        $this->assertSame(1, DB::table('public.migrations')->count());
    }

    public function test_rollback_removes_owned_objects_and_remigration_succeeds(): void
    {
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--force' => true]));
        $this->assertNull(DB::selectOne("SELECT to_regnamespace('library') AS name")->name);
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertSame(5, DB::table('library.category')->count());
    }

    public function test_foreign_keys_and_update_delete_actions_match_the_model(): void
    {
        $rows = DB::select("SELECT c.conrelid::regclass::text AS source, c.confrelid::regclass::text AS target,
            c.confdeltype AS deletion, c.confupdtype AS updating
            FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace
            WHERE n.nspname = 'library' AND c.contype = 'f' ORDER BY source, target");
        $this->assertCount(5, $rows);
        $actions = [];
        foreach ($rows as $row) {
            $this->assertSame('a', $row->updating);
            $actions[] = [$row->source, $row->target, $row->deletion];
        }
        $this->assertSame([
            ['book', 'category', 'r'],
            ['loan', 'member', 'r'],
            ['loan', '"user"', 'r'],
            ['loan_item', 'book', 'r'],
            ['loan_item', 'loan', 'c'],
        ], $actions);
        $this->fixtures();
        foreach ([
            ['library.book', 'category_id'],
            ['library.loan', 'member_id'],
            ['library.loan', 'loaned_by_user_id'],
            ['library.loan_item', 'loan_id'],
            ['library.loan_item', 'book_id'],
        ] as [$table, $column]) {
            $this->violation("UPDATE {$table} SET {$column} = 'ffffffff-ffff-4fff-8fff-ffffffffffff'", '23503');
        }
    }

    public function test_unique_keys_and_primary_keys_reject_duplicates(): void
    {
        $this->fixtures();
        foreach (['category', 'book', 'member', '"user"', 'loan', 'loan_item'] as $table) {
            $this->violation("INSERT INTO library.{$table} SELECT * FROM library.{$table} LIMIT 1", '23505');
        }
        $this->violation("UPDATE library.category SET name = 'Novela' WHERE name = 'Ciencia'", '23505');
        $this->violation("INSERT INTO library.member VALUES ('20000000-0000-4000-8000-000000000002', 'Other', 'DOC-1')", '23505');
        $this->violation("INSERT INTO library.\"user\" VALUES ('30000000-0000-4000-8000-000000000002', 'librarian', 'test-hash', 'librarian')", '23505');
        $this->violation("INSERT INTO library.loan_item SELECT '50000000-0000-4000-8000-000000000002', loan_id, book_id,
            book_title, isbn, category_name, due_at, returned_at FROM library.loan_item", '23505');
    }

    public function test_available_copy_range_checks_lower_and_upper_bounds(): void
    {
        $this->fixtures();
        $this->violation('UPDATE library.book SET available_copies = -1', '23514');
        $this->violation('UPDATE library.book SET available_copies = total_copies + 1', '23514');
        DB::statement('UPDATE library.book SET available_copies = 0');
        $this->assertSame(0, DB::table('library.book')->value('available_copies'));
        DB::statement('UPDATE library.book SET available_copies = total_copies');
        $this->assertSame(1, DB::table('library.book')->value('available_copies'));
    }

    public function test_soft_deletion_allows_isbn_reuse_without_deleting_history(): void
    {
        $this->fixtures();
        $insert = "INSERT INTO library.book SELECT '10000000-0000-4000-8000-000000000002', title, author, isbn,
            category_id, total_copies, available_copies, cover_key, NULL FROM library.book";
        $this->violation($insert, '23505');
        DB::statement('UPDATE library.book SET deleted_at = CURRENT_TIMESTAMP');
        DB::statement($insert);
        $this->assertSame(2, DB::table('library.book')->count());
        $this->assertSame(1, DB::table('library.book')->whereNull('deleted_at')->count());
        $this->assertSame(1, DB::table('library.loan_item')->count());
        $this->violation("DELETE FROM library.book WHERE id = '10000000-0000-4000-8000-000000000001'", '23503');
    }

    public function test_history_survives_catalog_changes_and_restrictive_deletes(): void
    {
        $this->fixtures();
        DB::statement("UPDATE library.book SET title = 'New title', category_id = '22222222-2222-4222-8222-222222222222'");
        DB::statement("UPDATE library.category SET name = 'Changed' WHERE name = 'Novela'");
        $item = DB::table('library.loan_item')->first();
        $this->assertSame('Original title', $item->book_title);
        $this->assertSame('9780307474728', $item->isbn);
        $this->assertSame('Novela', $item->category_name);
        $this->assertNull($item->returned_at);
        foreach (['member', '"user"', 'book'] as $table) {
            $this->violation("DELETE FROM library.{$table}", '23503');
        }
        $this->violation("DELETE FROM library.category WHERE name = 'Ciencia'", '23503');
        DB::statement('DELETE FROM library.loan');
        $this->assertSame(0, DB::table('library.loan_item')->count());
    }

    public function test_access_indexes_match_the_documented_predicates(): void
    {
        $indexes = DB::select("SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = 'library'");
        $definitions = [];
        foreach ($indexes as $index) {
            $definitions[$index->indexname] = $index->indexdef;
        }
        $this->assertStringContainsString('(isbn) WHERE (deleted_at IS NULL)', $definitions['uq_book_active_isbn']);
        $this->assertStringContainsString('(category_id, title) WHERE (deleted_at IS NULL)', $definitions['ix_book_active_category_title']);
        $this->assertStringContainsString('(loaned_at)', $definitions['ix_loan_loaned_at']);
        $this->assertStringContainsString('(book_id)', $definitions['ix_loan_item_book']);
        $this->assertStringContainsString('(due_at) WHERE (returned_at IS NULL)', $definitions['ix_loan_item_open_due']);
        $this->assertFalse(collect($definitions)->contains(fn (string $definition): bool => str_contains($definition, 'password_hash')));
    }

    public function test_nullability_and_uuid_storage_are_enforced(): void
    {
        $this->fixtures();
        $this->violation('UPDATE library.book SET title = NULL', '23502');
        $this->violation("UPDATE library.member SET id = 'not-a-uuid'", '22P02');
        DB::statement('UPDATE library.book SET cover_key = NULL, deleted_at = NULL');
        $this->assertNull(DB::table('library.book')->value('cover_key'));
    }

    private function fixtures(): void
    {
        DB::unprepared(<<<'SQL'
            INSERT INTO library.book VALUES (
                '10000000-0000-4000-8000-000000000001', 'Original title', 'Author', '9780307474728',
                '11111111-1111-4111-8111-111111111111', 1, 1, NULL, NULL
            );
            INSERT INTO library.member VALUES ('20000000-0000-4000-8000-000000000001', 'Member', 'DOC-1');
            INSERT INTO library."user" VALUES ('30000000-0000-4000-8000-000000000001', 'librarian', 'test-hash', 'librarian');
            INSERT INTO library.loan VALUES (
                '40000000-0000-4000-8000-000000000001', '20000000-0000-4000-8000-000000000001',
                '30000000-0000-4000-8000-000000000001', '2026-10-01T12:00:00Z'
            );
            INSERT INTO library.loan_item VALUES (
                '50000000-0000-4000-8000-000000000001', '40000000-0000-4000-8000-000000000001',
                '10000000-0000-4000-8000-000000000001', 'Original title', '9780307474728', 'Novela',
                '2026-10-15T12:00:00Z', NULL
            );
            SQL);
    }

    private function violation(string $sql, string $state): void
    {
        try {
            DB::transaction(fn () => DB::statement($sql));
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->errorInfo[0]);

            return;
        }

        $this->fail('Expected PostgreSQL constraint violation '.$state);
    }
}
