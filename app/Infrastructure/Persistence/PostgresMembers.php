<?php

namespace App\Infrastructure\Persistence;

use App\Application\Ports\Members;
use App\Domain\RuleViolation;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PostgresMembers implements Members
{
    public function find(string $id): ?array
    {
        $row = DB::table('library.member')->where('id', $id)->first();
        if ($row === null) {
            return null;
        }
        $active = DB::table('library.loan_item as i')->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.member_id', $id)->whereNull('i.returned_at')->count();

        return ['id' => $row->id, 'full_name' => $row->full_name, 'document_number' => $row->document_number, 'active_loans' => $active];
    }

    public function add(string $id, string $name, string $document): void
    {
        try {
            DB::table('library.member')->insert(['id' => $id, 'full_name' => $name, 'document_number' => $document]);
        } catch (QueryException $error) {
            if (($error->errorInfo[0] ?? null) === '23505') {
                throw new RuleViolation('DOCUMENT_TAKEN', 'El documento ya está registrado.');
            }
            throw $error;
        }
    }

    public function search(?string $query, int $page, int $size): array
    {
        $builder = DB::table('library.member');
        if ($query !== null && $query !== '') {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
            $builder->where(fn (Builder $q) => $q->whereRaw('full_name ILIKE ?', [$pattern])->orWhereRaw('document_number ILIKE ?', [$pattern]));
        }
        $total = (clone $builder)->count();
        $items = $builder->orderBy('full_name')->orderBy('id')->offset(($page - 1) * $size)->limit($size)
            ->get(['id', 'full_name', 'document_number'])->map(fn (object $row): array => (array) $row)->all();

        return compact('items', 'page', 'size', 'total');
    }
}
