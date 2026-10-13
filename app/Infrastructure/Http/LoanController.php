<?php

namespace App\Infrastructure\Http;

use App\Application\LendBooks;
use App\Application\Ports\Clock;
use App\Application\Ports\LoanQueries;
use App\Application\ReturnItem;
use App\Application\ViewLoan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoanController
{
    public function create(Request $request, LendBooks $useCase): JsonResponse
    {
        $data = $request->validate([
            'member_id' => 'required|uuid', 'items' => 'required|array|min:1|max:3',
            'items.*.book_id' => 'required|uuid',
        ]);
        $loan = $useCase->execute($data['member_id'], $request->attributes->get('operator_id'), array_column($data['items'], 'book_id'));

        return response()->json($loan, 201);
    }

    public function index(Request $request, LoanQueries $queries, Clock $clock): JsonResponse
    {
        [$page, $size] = Pagination::read($request);
        $data = $request->validate([
            'member_id' => 'sometimes|nullable|uuid', 'status' => 'sometimes|nullable|in:open,overdue,closed',
            'from' => 'prohibited', 'to' => 'prohibited',
        ], ['from.prohibited' => 'El formato del filtro de fechas requiere confirmación.', 'to.prohibited' => 'El formato del filtro de fechas requiere confirmación.']);

        return response()->json($queries->search($data['member_id'] ?? null, $data['status'] ?? null, $page, $size, $clock->now()));
    }

    public function show(string $id, ViewLoan $useCase): JsonResponse
    {
        return response()->json($useCase->execute($id));
    }

    public function returnItem(string $loanId, string $itemId, ReturnItem $useCase): JsonResponse
    {
        return response()->json($useCase->execute($loanId, $itemId));
    }
}
