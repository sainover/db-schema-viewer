<?php

use Illuminate\Support\Facades\Route;
use Sainover\DbSchemaViewer\Http\Controllers\SchemaController;

Route::prefix(config('db-schema-viewer.path', 'db-schema-viewer'))
    ->middleware(config('db-schema-viewer.middleware', ['web']))
    ->name('db-schema-viewer.')
    ->group(function () {

        // Main page
        Route::get('/', SchemaController::class . '@index')
            ->name('index');
    });
