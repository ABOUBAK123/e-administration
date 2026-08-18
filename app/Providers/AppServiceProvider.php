<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Schema::defaultStringLength(125);

        // Sur production derrière un reverse proxy, forcer le bon domaine et HTTPS
        // pour que route() génère les bonnes URLs dans les QR codes et les emails.
        if ($this->app->environment('production')) {
            $appUrl = config('app.url');
            if ($appUrl) {
                URL::forceRootUrl($appUrl);
            }
            URL::forceScheme('https');
        }
    }
}
