<?php

namespace App\Application;

use App\Domain\Book;

final readonly class BookSnapshot
{
    public function __construct(public Book $book, public string $version) {}
}
