<?php

use Illuminate\Support\Facades\Route;
use Sainover\ERDiagram\Http\Controllers\SchemaController;

Route::prefix(config('erdiagram.path', 'erdiagram'))
    ->middleware(config('erdiagram.middleware', ['web']))
    ->name('erdiagram.')
    ->group(function () {

        // Main page
        Route::get('/', SchemaController::class . '@index')
            ->name('index');
    });
