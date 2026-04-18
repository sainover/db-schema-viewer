<?php

declare(strict_types=1);

namespace Sainover\ERDiagram;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'erdiagram');
        $this->publishes([
            __DIR__.'/../config/erdiagram.php' => config_path('erdiagram.php'),
        ]);
    }
}
