<?php

namespace Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SchemaRules
{
    /** @return list<string> */
    public static function tablesWithoutIndex(?string $connection = null): array
    {
        $schema = Schema::connection($connection);
        $searchPath = $schema->getConnection()->getConfig('search_path');

        return collect($schema->getTableListing(is_string($searchPath) ? $searchPath : null, false))
            ->reject(fn (string $table) => $schema->getIndexes($table) !== [])
            ->values()
            ->all();
    }

    /** @param array<string, list<string>> $declared
     * @return list<string> */
    public static function missingIndexes(array $declared): array
    {
        $missing = [];

        foreach ($declared as $table => $columns) {
            $found = collect(Schema::getIndexes($table))
                ->reject(fn (array $index) => $index['primary'])
                ->contains(fn (array $index) => $index['columns'] === $columns);

            if (! $found) {
                $missing[] = $table.'('.implode(',', $columns).')';
            }
        }

        return $missing;
    }

    public static function lacksFillable(Model $model): bool
    {
        return $model->getFillable() === [] || $model->getGuarded() === [];
    }

    /** @return list<class-string<Model>> */
    public static function models(): array
    {
        return collect(glob(app_path('Models/*.php')))
            ->map(fn (string $file) => 'App\\Models\\'.basename($file, '.php'))
            ->filter(fn (string $class) => is_subclass_of($class, Model::class))
            ->values()
            ->all();
    }
}
