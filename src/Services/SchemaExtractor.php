<?php

declare(strict_types=1);

namespace Sainover\DbSchemaViewer\Services;

use Illuminate\Support\Facades\Schema;

class SchemaExtractor
{
    public function getTables(): array
    {
        $tables = [];

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            $tables[$name] = [
                'columns' => Schema::getColumns($name),
                'indexes' => Schema::getIndexes($name),
                'foreign_keys' => Schema::getForeignKeys($name),
            ];
        }

        return $tables;
    }
}
