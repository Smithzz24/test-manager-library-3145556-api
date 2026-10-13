<?php

namespace App\Infrastructure\Persistence;

use App\Application\BookSnapshot;
use App\Application\Ports\Catalog;
use App\Domain\Book;
use App\Domain\RuleViolation;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PostgresCatalog implements Catalog
{
    public function find(string $id): ?BookSnapshot
    {
        $row = $this->active()->where('b.id', $id)->first();

        return $row === null ? null : new BookSnapshot($this->book($row), $row->row_version);
    }

    public function add(Book $book): void
    {
        try {
            DB::table('library.book')->insert(['id' => $book->id, ...$this->values($book)]);
        } catch (QueryException $error) {
            if (($error->errorInfo[0] ?? null) === '23505') {
                throw new RuleViolation('ISBN_TAKEN', 'El ISBN ya pertenece a un libro activo.');
            }
            throw $error;
        }
    }

    public function save(Book $book, string $version): bool
    {
        return DB::table('library.book')->where('id', $book->id)->whereNull('deleted_at')
            ->whereRaw('xmin::text = ?', [$version])->update($this->values($book)) === 1;
    }

    public function categories(): array
    {
        return DB::table('library.category')->orderBy('name')->get(['id', 'name'])
            ->map(fn (object $row): array => ['id' => $row->id, 'name' => $row->name])->all();
    }

    public function categoryName(string $id): ?string
    {
        return DB::table('library.category')->where('id', $id)->value('name');
    }

    public function search(?string $query, ?string $categoryId, int $page, int $size): array
    {
        $builder = $this->active();
        if ($query !== null && $query !== '') {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
            $builder->where(fn (Builder $q) => $q->whereRaw('b.title ILIKE ?', [$pattern])->orWhereRaw('b.author ILIKE ?', [$pattern]));
        }
        if ($categoryId !== null) {
            $builder->where('b.category_id', $categoryId);
        }
        $total = (clone $builder)->count();
        $items = $builder->orderBy('b.title')->orderBy('b.id')->offset(($page - 1) * $size)->limit($size)
            ->get()->map(fn (object $row): Book => $this->book($row))->all();

        return compact('items', 'page', 'size', 'total');
    }

    private function active(): Builder
    {
        return DB::table('library.book as b')->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->whereNull('b.deleted_at')->select('b.*', 'c.name as category_name')->selectRaw('b.xmin::text AS row_version');
    }

    private function book(object $row): Book
    {
        return new Book($row->id, $row->title, $row->author, $row->isbn, $row->category_id,
            $row->category_name, $row->total_copies, $row->available_copies, $row->cover_key,
            $row->deleted_at === null ? null : new DateTimeImmutable($row->deleted_at));
    }

    /** @return array<string, mixed> */
    private function values(Book $book): array
    {
        return [
            'title' => $book->title, 'author' => $book->author, 'isbn' => $book->isbn,
            'category_id' => $book->categoryId, 'total_copies' => $book->totalCopies,
            'available_copies' => $book->availableCopies, 'cover_key' => $book->coverKey,
            'deleted_at' => $book->deletedAt?->format('Y-m-d H:i:s.uP'),
        ];
    }
}
