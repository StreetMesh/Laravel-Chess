<?php

namespace StreetMesh\Chess;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;

class ChessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Games::class);
    }

    public function boot(): void
    {
        $this->app->make(Capabilities::class)->register(new ChessCapability);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'chess');

        /*
         * Livewire keeps a register of component namespaces separate from
         * Blade's, and consults only what `addNamespace` gave it. Both are
         * needed for a package to ship a screen.
         */
        Livewire::addNamespace('chess', viewPath: __DIR__.'/../resources/views/livewire');

        $this->app['router']
            ->middleware('web')
            ->group(__DIR__.'/../routes/web.php');
    }
}
