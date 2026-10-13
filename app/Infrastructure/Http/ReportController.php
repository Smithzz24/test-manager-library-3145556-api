<?php

namespace App\Infrastructure\Http;

use App\Application\MostBorrowed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportController
{
    public function __invoke(Request $request, MostBorrowed $useCase): JsonResponse
    {
        $data = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d']);

        return response()->json($useCase->execute($data['from'], $data['to']));
    }
}
