<?php

namespace App\Infrastructure\Http;

use App\Domain\Book;

final class BookPresenter
{
    /** @return array<string, mixed> */
    public static function show(Book $book): array
    {
        return [
            'id' => $book->id, 'title' => $book->title, 'author' => $book->author, 'isbn' => $book->isbn,
            'category' => ['id' => $book->categoryId, 'name' => $book->categoryName],
            'total_copies' => $book->totalCopies, 'available_copies' => $book->availableCopies, 'cover_key' => $book->coverKey,
        ];
    }
}
