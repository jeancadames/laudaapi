<?php

namespace App\Console\Commands;

use App\Jobs\SyncObligationInstancesForCompany;
use Illuminate\Console\Command;

class ComplianceSyncCommand extends Command
{
    protected $signature = 'compliance:sync
        {company_id : ID de la empresa}
        {--future=18 : Meses hacia adelante}
        {--past=3 : Meses hacia atrás}
        {--soon=7 : Ventana due_soon (días)}
        {--sync : Ejecutar inline (sin cola)}
    ';

    protected $description = 'Genera/actualiza obligation_instances desde tenant_obligations (MySQL upsert).';

    public function handle(): int
    {
        $companyId = (int)$this->argument('company_id');
        $future = (int)$this->option('future');
        $past = (int)$this->option('past');
        $soon = (int)$this->option('soon');

        $job = new SyncObligationInstancesForCompany($companyId, $future, $past, $soon);

        if ($this->option('sync')) {
            $this->info("Running sync inline for company_id={$companyId}...");
            $job->handle(app(\App\Services\Compliance\DueDateCalculator::class));
            $this->info('Done.');
            return self::SUCCESS;
        }

        dispatch($job);
        $this->info("Dispatched sync for company_id={$companyId}.");
        return self::SUCCESS;
    }
}
