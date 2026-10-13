<?php

namespace App\Infrastructure\Http;

use Illuminate\Http\Request;

final class Pagination
{
    /** @return array{int, int} */
    public static function read(Request $request): array
    {
        $values = $request->validate(['page' => 'sometimes|integer|min:1', 'size' => 'sometimes|integer|between:1,50']);

        return [(int) ($values['page'] ?? 1), (int) ($values['size'] ?? 20)];
    }
}
