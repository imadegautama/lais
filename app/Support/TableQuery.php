<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * One page of a server-driven AppDataTable, read from the URL:
 * ?cari=teks, ?urut=kolom or ?urut=-kolom, ?halaman=2, plus filter keys (?jenis[]=bahan).
 * Bad params are ignored rather than rejected, because these are bookmarkable GET URLs.
 */
final class TableQuery
{
    public const PER_PAGE = 20;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  already scoped to the business
     * @param  Closure(TModel): array<string, mixed>  $map
     * @param  array<string, string|Closure(Builder<TModel>, 'asc'|'desc'): mixed>  $sorts  url key => column or orderer
     * @param  array<string, array{0: string|Closure(Builder<TModel>, non-empty-list<string>): mixed, 1: list<string>}>  $filters  url key => [column or custom filter, allowed values]
     * @param  list<literal-string>  $search  SQL expressions written in code, never user input
     * @param  (Closure(Builder<TModel>): array<string, mixed>)|null  $summary  totals over the filtered rows (all pages), so they follow the filters on a table-only reload
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, perPage: int, total: int, lastPage: int}, query: array<string, string|list<string>>, summary?: array<string, mixed>}
     */
    public static function paginate(
        Request $request,
        Builder $query,
        Closure $map,
        array $sorts,
        string $defaultSort,
        array $filters = [],
        array $search = [],
        int $perPage = self::PER_PAGE,
        ?Closure $summary = null,
    ): array {
        $state = [];

        foreach ($filters as $key => [$column, $allowed]) {
            $requested = $request->query($key, []);
            $values = array_values(array_filter(
                is_array($requested) ? $requested : [$requested],
                fn (mixed $value): bool => is_string($value) && in_array($value, $allowed, true),
            ));

            if ($values !== []) {
                is_string($column) ? $query->whereIn($column, $values) : $column($query, $values);
            }

            $state[$key] = $values;
        }

        $term = is_string($request->query('cari')) ? mb_substr(trim($request->query('cari')), 0, 100) : '';

        if ($term !== '' && $search !== []) {
            // shortcut: % and _ in the term are not escaped, they only widen the match.
            $like = '%'.mb_strtolower($term).'%';
            $query->where(function (Builder $where) use ($search, $like) {
                foreach ($search as $expression) {
                    $where->orWhereRaw("lower({$expression}) like ?", [$like]);
                }
            });
        }

        $totals = $summary?->__invoke(clone $query);

        $sort = is_string($request->query('urut')) ? $request->query('urut') : $defaultSort;

        if (! array_key_exists(ltrim($sort, '-'), $sorts)) {
            $sort = $defaultSort;
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $orderer = $sorts[ltrim($sort, '-')];
        is_string($orderer) ? $query->orderBy($orderer, $direction) : $orderer($query, $direction);
        // Ties keep a stable order in the same direction, so no row shows up on two pages
        // and, newest first, the last one recorded in the same second comes first.
        $query->orderBy($query->getModel()->getQualifiedKeyName(), $direction);

        $page = $query->paginate($perPage, ['*'], 'halaman');

        if ($page->currentPage() > $page->lastPage()) {
            $page = $query->paginate($perPage, ['*'], 'halaman', $page->lastPage());
        }

        $result = [
            'data' => array_values(array_map($map, $page->items())),
            'meta' => [
                'page' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
                'lastPage' => $page->lastPage(),
            ],
            'query' => ['cari' => $term, 'urut' => $sort] + $state,
        ];

        return $totals === null ? $result : $result + ['summary' => $totals];
    }
}
