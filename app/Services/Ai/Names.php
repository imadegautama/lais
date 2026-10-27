<?php

namespace App\Services\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Finds the record a name from the model means: an exact name or alias
 * first, then the one record whose name contains it. Anything else is no
 * match, so a guess never picks the wrong item.
 */
final class Names
{
    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $records
     * @param  callable(TModel): list<string>  $namesOf
     * @return TModel|null
     */
    public static function match(Collection $records, mixed $query, callable $namesOf): ?Model
    {
        $wanted = self::normalize($query);

        if ($wanted === '') {
            return null;
        }

        $partial = null;
        $partials = 0;

        foreach ($records as $record) {
            $names = array_map(self::normalize(...), $namesOf($record));

            if (in_array($wanted, $names, true)) {
                return $record;
            }

            if (str_contains($names[0] ?? '', $wanted)) {
                $partial = $record;
                $partials++;
            }
        }

        return $partials === 1 ? $partial : null;
    }

    private static function normalize(mixed $name): string
    {
        return is_string($name) ? mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? '')) : '';
    }
}
