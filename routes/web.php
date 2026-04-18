<?php

use Illuminate\Support\Facades\Route;
use Sainover\DbSchemaViewer\Http\Controllers\LayoutController;
use Sainover\DbSchemaViewer\Http\Controllers\SchemaController;

Route::prefix(config('db-schema-viewer.path', 'db-schema-viewer'))
    ->middleware(config('db-schema-viewer.middleware', ['web']))
    ->name('db-schema-viewer.')
    ->group(function () {

        // Main page
        Route::get('/', SchemaController::class . '@index')
            ->name('index');

        // Layouts CRUD
        Route::prefix('layouts')->name('layouts.')->group(function () {
            Route::get('/', [LayoutController::class, 'index'])->name('index');
            Route::post('/', [LayoutController::class, 'store'])->name('store');
            Route::get('/{slug}', [LayoutController::class, 'show'])->name('show');
            Route::put('/{slug}', [LayoutController::class, 'update'])->name('update');
            Route::delete('/{slug}', [LayoutController::class, 'destroy'])->name('destroy');
            Route::post('/{slug}/default', [LayoutController::class, 'setDefault'])->name('default');
        });
    });
