<?php

namespace App\Providers;

use App\Support\WatermarkFoto;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Font Hind (sama dengan website) untuk logo dan teks watermark selfie
        $this->app->singleton(WatermarkFoto::class, fn () => new WatermarkFoto(resource_path('fonts')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
