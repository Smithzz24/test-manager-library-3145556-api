<?php

namespace App\Application;

use App\Application\Ports\Catalog;
use App\Domain\Book;
use App\Domain\RuleViolation;

final readonly class ReviseBook
{
    public function __construct(private Catalog $catalog) {}

    public function execute(string $id, string $title, string $author, string $categoryId, int $total): Book
    {
        $snapshot = $this->catalog->find($id) ?? throw new RuleViolation('NOT_FOUND', 'El libro no existe.');
        $category = $this->catalog->categoryName($categoryId)
            ?? throw new RuleViolation('VALIDATION_ERROR', 'La categoría no existe.');
        $book = $snapshot->book->revise($title, $author, $categoryId, $category, $total);
        if (! $this->catalog->save($book, $snapshot->version)) {
            throw new RuleViolation('BOOK_NOT_AVAILABLE', 'El libro cambió durante la operación; vuelve a intentarlo.');
        }

        return $book;
    }
}
