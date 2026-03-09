<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ResetLocalApp extends Command
{
    protected $signature = 'app:reset-local {--force : Permite ejecutar fuera de local/testing}';
    protected $description = 'Reinicia la app completa en local: limpia storage/app/private, limpia caches, refresca BD y reinicia queue';

    public function handle(): int
    {
        $allowed = app()->environment(['local', 'testing']);

        if (! $allowed && ! $this->option('force')) {
            $this->error('Este comando solo se puede ejecutar en local/testing.');
            $this->line('Si realmente quieres ejecutarlo fuera de esos entornos, usa --force.');
            return self::FAILURE;
        }

        $privatePath = storage_path('app/private');

        $this->warn('Iniciando reseteo completo de la app...');

        // 1) Limpiar todo lo que está dentro de storage/app/private
        $this->components->task('Limpiando storage/app/private', function () use ($privatePath) {
            if (! File::exists($privatePath)) {
                File::makeDirectory($privatePath, 0755, true);
                return true;
            }

            File::cleanDirectory($privatePath);
            return true;
        });

        // 2) Limpiar optimizaciones / caches compiladas
        $this->components->task('Ejecutando optimize:clear', function () {
            Artisan::call('optimize:clear');
            $this->output->write(Artisan::output());
            return true;
        });

        // 3) Refrescar base de datos con seed
        $this->components->task('Ejecutando migrate:fresh --seed', function () {
            Artisan::call('migrate:fresh', [
                '--seed' => true,
                '--force' => true,
            ]);
            $this->output->write(Artisan::output());
            return true;
        });

        // 4) Reiniciar workers de queue
        $this->components->task('Ejecutando queue:restart', function () {
            Artisan::call('queue:restart');
            $this->output->write(Artisan::output());
            return true;
        });

        $this->newLine();
        $this->info('Reset completado correctamente.');

        return self::SUCCESS;
    }
}