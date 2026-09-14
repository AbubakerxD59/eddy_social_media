<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
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
        $this->configureDefaults();
        $this->configureEmailVerification();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // utf8mb4 indexes on older MySQL/MariaDB (and MyISAM) cap at 767–1000 bytes.
        Schema::defaultStringLength(191);

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): Password {
            $rule = Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();

            if (app()->isProduction()) {
                $rule->uncompromised();
            }

            return $rule;
        });
    }

    protected function configureEmailVerification(): void
    {
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $name = method_exists($notifiable, 'displayName')
                ? $notifiable->displayName()
                : ($notifiable->name ?? 'there');

            return (new MailMessage)
                ->subject('Verify your '.config('app.name').' account')
                ->greeting('Hi '.$name.',')
                ->line('Thanks for creating an account. Click the button below to verify your email and open your dashboard.')
                ->action('Verify email address', $url)
                ->line('If you did not create this account, you can ignore this email.');
        });
    }
}
