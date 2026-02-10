<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class LatestMunicipioDemografia
{
    public static function latestYearSubquery(): QueryBuilder
    {
        return DB::table('municipio_demografia as md_year_source')
            ->selectRaw('md_year_source.municipio_id, MAX(md_year_source.ano_ref) as ano_ref')
            ->whereNull('md_year_source.deleted_at')
            ->groupBy('md_year_source.municipio_id');
    }

    public static function join(Builder $query, string $municipioColumn, string $yearAlias = 'md_year', string $demografiaAlias = 'md_latest'): Builder
    {
        $query->leftJoinSub(self::latestYearSubquery(), $yearAlias, function ($join) use ($municipioColumn, $yearAlias): void {
            $join->on("{$yearAlias}.municipio_id", '=', $municipioColumn);
        });

        return $query->leftJoin("municipio_demografia as {$demografiaAlias}", function ($join) use ($yearAlias, $demografiaAlias): void {
            $join->on("{$demografiaAlias}.municipio_id", '=', "{$yearAlias}.municipio_id")
                ->on("{$demografiaAlias}.ano_ref", '=', "{$yearAlias}.ano_ref")
                ->whereNull("{$demografiaAlias}.deleted_at");
        });
    }
}
