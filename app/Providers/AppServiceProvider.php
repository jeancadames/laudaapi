<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Queue\Events\JobFailed;

use App\Services\Dgii\DgiiAuthClient;
use App\Services\Dgii\HttpDgiiAuthClient;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use App\Services\ErrorLogService;
use App\Http\Responses\LoginResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Sobrescribir la respuesta de login de Fortify
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->bind(DgiiAuthClient::class, HttpDgiiAuthClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Queue::failing(function (JobFailed $event) {
            try {
                app(ErrorLogService::class)->capture($event->exception, [
                    'level' => 'error',
                    'tags' => ['queue', $event->connectionName],
                    'context' => [
                        'job_name' => $event->job->resolveName(),
                        'queue' => $event->job->getQueue(),
                        'connection' => $event->connectionName,
                        'payload' => $event->job->payload(),
                    ],
                ]);
            } catch (\Throwable) {
                // no-op
            }
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn(): ?Password => app()->isProduction()
                ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
                : null
        );
    }
}
