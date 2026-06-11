<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DeployCommand extends Command
{
    protected $signature = 'app:deploy';

    protected $description = 'Deploy LaudaAPI application';

    public function handle(): int
    {
        $this->info('🚀 Iniciando deploy de LaudaAPI...');
        $this->newLine();

        $isProduction = app()->isProduction();

        $commands = [
            ['git', 'pull', '--ff-only', 'origin', 'master'],
            ['composer', 'install', '--no-interaction', '--prefer-dist', '--optimize-autoloader'],
            ['npm', 'ci'],
            ['npm', 'run', 'build'],
            ['php', 'artisan', 'migrate', '--force'],
            ['php', 'artisan', 'optimize:clear'],
            ['php', 'artisan', 'config:cache'],
            ['php', 'artisan', 'route:cache'],
            ['php', 'artisan', 'view:cache'],
        ];

        foreach ($commands as $command) {
            $this->line('➜ ' . implode(' ', $command));

            $process = new Process($command, base_path());
            $process->setTimeout(600);
            $process->run(function ($type, $buffer) {
                echo $buffer;
            });

            if (! $process->isSuccessful()) {
                $this->error('❌ Falló el comando: ' . implode(' ', $command));
                return self::FAILURE;
            }

            $this->newLine();
        }

        if ($isProduction) {
            $this->info('✅ Deploy productivo completado exitosamente.');
        } else {
            $this->info('✅ Deploy completado exitosamente.');
        }

        return self::SUCCESS;
    }
}