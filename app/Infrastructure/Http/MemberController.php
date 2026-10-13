<?php

namespace App\Infrastructure\Http;

use App\Application\CreateMember;
use App\Application\Ports\Members;
use App\Domain\RuleViolation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MemberController
{
    public function index(Request $request, Members $members): JsonResponse
    {
        [$page, $size] = Pagination::read($request);
        $data = $request->validate(['q' => 'sometimes|nullable|string']);

        return response()->json($members->search($data['q'] ?? null, $page, $size));
    }

    public function create(Request $request, CreateMember $useCase): JsonResponse
    {
        $data = $request->validate(['full_name' => 'required|string|max:200', 'document_number' => 'required|string|max:30']);

        return response()->json($useCase->execute($data['full_name'], $data['document_number']), 201);
    }

    public function show(string $id, Members $members): JsonResponse
    {
        return response()->json($members->find($id) ?? throw new RuleViolation('NOT_FOUND', 'El socio no existe.'));
    }
}
