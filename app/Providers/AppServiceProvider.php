<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Compatibilidad con MySQL/MariaDB del hosting (índices de máx. 1000 bytes)
        Schema::defaultStringLength(191);
        // Por defecto se trabaja con la cuenta principal (sitio web, comandos); el panel activa la de cada usuario
        \App\Support\Cuentas::reiniciar();
        try {
            \App\Support\Cuentas::activar(\App\Support\Cuentas::principalId());
        } catch (\Throwable $e) {
            // antes de migrar
        }
    }
}
