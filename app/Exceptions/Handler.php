<?php

namespace App\Exceptions;

use App\Services\ErrorLogService;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        // Puedes añadir aquí excepciones que no quieras guardar
        // \Illuminate\Validation\ValidationException::class,
        // \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ];

    /**
     * Report or log an exception.
     */
    public function report(Throwable $e): void
    {
        // Primero que Laravel haga su reporte normal (logs, Sentry, etc)
        parent::report($e);

        // Evita reportar cosas "normales" (validation, auth, etc.) usando la lógica del framework
        if ($this->shouldntReport($e)) {
            return;
        }

        try {
            app(ErrorLogService::class)->capture($e, [
                'level' => 'error',
                // NO necesitas status_code aquí, el service lo detecta.
                'context' => [
                    // Si quieres, puedes añadir input filtrado (ojo: request() puede no existir en CLI)
                    // 'input' => app()->bound('request') ? request()->except(['password','password_confirmation','token']) : null,
                ],
            ]);
        } catch (Throwable) {
            // Nunca rompas el request por el logger
        }
    }
}
