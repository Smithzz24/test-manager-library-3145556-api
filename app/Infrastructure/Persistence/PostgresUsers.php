<?php

namespace App\Infrastructure\Persistence;

use App\Application\Ports\Users;
use App\Domain\Operator;
use App\Domain\RuleViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PostgresUsers implements Users
{
    public function byUsername(string $username): ?Operator
    {
        return $this->map(DB::table('library.user')->where('username', $username)->first());
    }

    public function byId(string $id): ?Operator
    {
        return $this->map(DB::table('library.user')->where('id', $id)->first());
    }

    public function add(Operator $user): void
    {
        try {
            DB::table('library.user')->insert([
                'id' => $user->id, 'username' => $user->username,
                'password_hash' => $user->passwordHash, 'role' => $user->role,
            ]);
        } catch (QueryException $error) {
            if (($error->errorInfo[0] ?? null) === '23505') {
                throw new RuleViolation('USERNAME_TAKEN', 'El nombre de usuario ya existe.');
            }
            throw $error;
        }
    }

    private function map(?object $row): ?Operator
    {
        return $row === null ? null : new Operator($row->id, $row->username, $row->password_hash, $row->role);
    }
}
