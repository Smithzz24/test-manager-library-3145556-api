<?php

namespace App\Application\Ports;

interface Members
{
    /** @return ?array{id: string, full_name: string, document_number: string, active_loans: int} */
    public function find(string $id): ?array;

    public function add(string $id, string $name, string $document): void;

    /** @return array{items: list<array{id: string, full_name: string, document_number: string}>, page: int, size: int, total: int} */
    public function search(?string $query, int $page, int $size): array;
}
