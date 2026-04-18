<?php

declare(strict_types=1);

namespace Sainover\DbSchemaViewer;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'db-schema-viewer');
        $this->publishes([
            __DIR__.'/../config/db-schema-viewer.php' => config_path('db-schema-viewer.php'),
        ]);
    }
}
