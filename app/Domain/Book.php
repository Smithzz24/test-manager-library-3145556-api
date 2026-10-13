<?php

namespace App\Domain;

use DateTimeImmutable;

final readonly class Book
{
    public string $title;

    public function __construct(
        public string $id,
        string $title,
        public string $author,
        public string $isbn,
        public string $categoryId,
        public string $categoryName,
        public int $totalCopies,
        public int $availableCopies,
        public ?string $coverKey = null,
        public ?DateTimeImmutable $deletedAt = null,
    ) {
        $this->title = trim($title);
        if ($this->title === '' || ! preg_match('/^[0-9]{13}$/D', $isbn)
            || $totalCopies < 1 || $availableCopies < 0 || $availableCopies > $totalCopies
            || $coverKey === '') {
            throw new RuleViolation('VALIDATION_ERROR', 'Los datos del libro no son válidos.');
        }
    }

    public function revise(string $title, string $author, string $categoryId, string $categoryName, int $total): self
    {
        return new self($this->id, $title, $author, $this->isbn, $categoryId, $categoryName,
            $total, $this->availableCopies + $total - $this->totalCopies, $this->coverKey, $this->deletedAt);
    }

    public function lend(): self
    {
        if ($this->availableCopies === 0 || $this->deletedAt !== null) {
            throw new RuleViolation('BOOK_NOT_AVAILABLE', 'No quedan ejemplares disponibles.');
        }

        return new self($this->id, $this->title, $this->author, $this->isbn, $this->categoryId,
            $this->categoryName, $this->totalCopies, $this->availableCopies - 1, $this->coverKey, $this->deletedAt);
    }

    public function returned(): self
    {
        return new self($this->id, $this->title, $this->author, $this->isbn, $this->categoryId,
            $this->categoryName, $this->totalCopies, $this->availableCopies + 1, $this->coverKey, $this->deletedAt);
    }

    public function withdraw(DateTimeImmutable $now, bool $hasOpenLoans): self
    {
        if ($hasOpenLoans) {
            throw new RuleViolation('BOOK_HAS_OPEN_LOANS', 'El libro tiene préstamos pendientes.');
        }

        return new self($this->id, $this->title, $this->author, $this->isbn, $this->categoryId,
            $this->categoryName, $this->totalCopies, $this->availableCopies, $this->coverKey, $now);
    }

    public function withCover(?string $key): self
    {
        return new self($this->id, $this->title, $this->author, $this->isbn, $this->categoryId,
            $this->categoryName, $this->totalCopies, $this->availableCopies, $key, $this->deletedAt);
    }
}
