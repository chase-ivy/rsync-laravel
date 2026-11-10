<?php

namespace App\Providers;

use App\Mail\BrevoTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
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
        // MAIL_MAILER=brevo sends email through Brevo's web API (see App\Mail\BrevoTransport).
        Mail::extend('brevo', fn () => new BrevoTransport((string) config('services.brevo.key')));

        // On the live site every link must be https:// (Render serves the app over HTTPS only).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}