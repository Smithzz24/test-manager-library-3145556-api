<?php

namespace App\Infrastructure\Http;

use App\Application\CreateBook;
use App\Application\Ports\Catalog;
use App\Application\ReviseBook;
use App\Application\WithdrawBook;
use App\Domain\RuleViolation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class BookController
{
    public function index(Request $request, Catalog $catalog): JsonResponse
    {
        [$page, $size] = Pagination::read($request);
        $data = $request->validate(['q' => 'sometimes|nullable|string', 'category_id' => 'sometimes|nullable|uuid']);
        $result = $catalog->search($data['q'] ?? null, $data['category_id'] ?? null, $page, $size);
        $result['items'] = array_map(BookPresenter::show(...), $result['items']);

        return response()->json($result);
    }

    public function categories(Catalog $catalog): JsonResponse
    {
        return response()->json($catalog->categories());
    }

    public function show(string $id, Catalog $catalog): JsonResponse
    {
        $snapshot = $catalog->find($id) ?? throw new RuleViolation('NOT_FOUND', 'El libro no existe.');

        return response()->json(BookPresenter::show($snapshot->book));
    }

    public function create(Request $request, CreateBook $useCase): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:250', 'author' => 'required|string|max:200',
            'isbn' => ['required', 'string', 'regex:/^[0-9]{13}$/D'], 'category_id' => 'required|uuid',
            'total_copies' => 'required|integer|min:1', 'available_copies' => 'prohibited', 'cover_key' => 'prohibited',
        ]);
        $book = $useCase->execute($data['title'], $data['author'], $data['isbn'], $data['category_id'], (int) $data['total_copies']);

        return response()->json(BookPresenter::show($book), 201);
    }

    public function update(Request $request, string $id, ReviseBook $useCase): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:250', 'author' => 'required|string|max:200',
            'category_id' => 'required|uuid', 'total_copies' => 'required|integer|min:1',
            'isbn' => 'prohibited', 'available_copies' => 'prohibited', 'cover_key' => 'prohibited',
        ]);
        $book = $useCase->execute($id, $data['title'], $data['author'], $data['category_id'], (int) $data['total_copies']);

        return response()->json(BookPresenter::show($book));
    }

    public function delete(string $id, WithdrawBook $useCase): Response
    {
        $useCase->execute($id);

        return response()->noContent();
    }
}
