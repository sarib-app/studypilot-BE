<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
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
        // This is an API-only backend (mobile app + admin SPA), so there's no
        // web "password.reset" route for the default notification to link to.
        // Point it at the app's deep link instead — the app screen reads the
        // token/email query params once the reset-password screen is wired to this API.
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return 'studypilot://reset-password?token='.$token.'&email='.urlencode($notifiable->email);
        });
    }
}
