<?php

namespace App\Application\Ports;

use App\Application\BookSnapshot;
use App\Domain\Book;

interface Catalog
{
    public function find(string $id): ?BookSnapshot;

    public function add(Book $book): void;

    public function save(Book $book, string $version): bool;

    /** @return list<array{id: string, name: string}> */
    public function categories(): array;

    public function categoryName(string $id): ?string;

    /** @return array{items: list<Book>, page: int, size: int, total: int} */
    public function search(?string $query, ?string $categoryId, int $page, int $size): array;
}
