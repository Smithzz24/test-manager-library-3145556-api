<?php

namespace App\Application;

use App\Application\Ports\Identifiers;
use App\Application\Ports\Members;

final readonly class CreateMember
{
    public function __construct(private Members $members, private Identifiers $ids) {}

    /** @return array{id: string, full_name: string, document_number: string} */
    public function execute(string $name, string $document): array
    {
        $id = $this->ids->next();
        $this->members->add($id, $name, $document);

        return ['id' => $id, 'full_name' => $name, 'document_number' => $document];
    }
}
