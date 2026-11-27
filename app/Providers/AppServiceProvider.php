<?php

namespace App\Providers;

use App\Mail\PhpMailTransport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // One password policy everywhere (sign up, admin-created users, password reset).
        Password::defaults(fn () => Password::min(8)->letters()->mixedCase()->numbers()->symbols());

        // MAIL_MAILER=phpmail sends with PHP's mail() function.
        Mail::extend('phpmail', fn () => new PhpMailTransport);

        RateLimiter::for('forgot-password', fn (Request $request) => Limit::perMinute(3)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip())
            ->response(fn () => back()->with('error', 'Too many reset requests. Please wait a minute and try again.')));
        RateLimiter::for('predict', fn (Request $request) => Limit::perMinute(30)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('ml-train', fn (Request $request) => Limit::perMinute(6)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('upload', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->id ?? $request->ip())));
    }
}
