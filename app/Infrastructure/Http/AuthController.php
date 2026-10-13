<?php

namespace App\Infrastructure\Http;

use App\Application\CreateLibrarian;
use App\Application\SignIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController
{
    public function login(Request $request, SignIn $useCase): JsonResponse
    {
        $data = $request->validate(['username' => 'required|string|max:120', 'password' => 'required|string']);

        return response()->json($useCase->execute($data['username'], $data['password']));
    }

    public function create(Request $request, CreateLibrarian $useCase): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string|max:120', 'password' => 'required|string', 'role' => 'required|in:librarian',
        ]);
        $user = $useCase->execute($data['username'], $data['password'], $data['role']);

        return response()->json(['id' => $user->id, 'username' => $user->username, 'role' => $user->role], 201);
    }
}
