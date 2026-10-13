<?php

namespace App\Application;

use App\Application\Ports\Catalog;
use App\Application\Ports\Identifiers;
use App\Domain\Book;
use App\Domain\RuleViolation;

final readonly class CreateBook
{
    public function __construct(private Catalog $catalog, private Identifiers $ids) {}

    public function execute(string $title, string $author, string $isbn, string $categoryId, int $total): Book
    {
        $category = $this->catalog->categoryName($categoryId)
            ?? throw new RuleViolation('VALIDATION_ERROR', 'La categoría no existe.');
        $book = new Book($this->ids->next(), $title, $author, $isbn, $categoryId, $category, $total, $total);
        $this->catalog->add($book);

        return $book;
    }
}
