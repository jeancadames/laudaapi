<?php

namespace App\Console\Commands;

use App\Services\Diagnosis\DiagnosisAssessmentReconciliationService;
use Illuminate\Console\Command;

class ReconcileDiagnosisAssessments extends Command
{
    protected $signature = 'lauda360:reconcile-assessments
        {--organization= : Limitar la reconciliación a una organización}
        {--apply : Aplicar los cambios. Sin esta opción el comando es solo dry-run}';

    protected $description =
        'Detecta y reconcilia diagnósticos publicados que fueron supersedidos prematuramente por un assessment no publicado.';

    public function handle(
        DiagnosisAssessmentReconciliationService $service
    ): int {
        $organizationOption = $this->option('organization');

        if (
            $organizationOption !== null
            && (
                ! ctype_digit((string) $organizationOption)
                || (int) $organizationOption <= 0
            )
        ) {
            $this->error(
                '--organization debe ser un ID entero positivo.'
            );

            return self::FAILURE;
        }

        $organizationId = $organizationOption !== null
            ? (int) $organizationOption
            : null;

        $apply = (bool) $this->option('apply');

        $this->newLine();
        $this->info('LAUDA 360 · Reconciliación de diagnósticos');
        $this->line(
            'Modo: '.($apply ? 'APPLY' : 'DRY-RUN')
        );

        if ($organizationId !== null) {
            $this->line(
                'Organización: '.$organizationId
            );
        } else {
            $this->line('Organización: todas');
        }

        $this->newLine();

        $candidates = $service->candidates($organizationId);

        if ($candidates->isEmpty()) {
            $this->info(
                'No se encontraron assessments que requieran reconciliación.'
            );

            $this->line('Candidatos: 0');
            $this->line('Cambios aplicados: 0');

            return self::SUCCESS;
        }

        $changed = 0;

        foreach ($candidates as $official) {
            $description = $service->describe($official);

            $this->line(
                str_repeat('=', 70)
            );

            $this->line(
                'Organización: '.
                ($description['organization_id'] ?? 'NULL').
                ' · '.
                ($description['organization_name'] ?? 'Sin nombre')
            );

            $this->newLine();

            $this->line(
                'Official assessment: #'.
                $description['official_assessment_id']
            );

            $this->line(
                '  status: '.
                ($description['official_status'] ?? 'NULL')
            );

            $this->line(
                '  published_at: '.
                ($description['official_published_at'] ?? 'NULL')
            );

            $this->line(
                '  is_active: '.
                (
                    $description['official_is_active']
                        ? 'true'
                        : 'false'
                )
            );

            $this->line(
                '  inactivated_at: '.
                ($description['official_inactivated_at'] ?? 'NULL')
            );

            $this->line(
                '  superseded_by: '.
                ($description['official_superseded_by'] ?? 'NULL')
            );

            $this->newLine();

            $this->line(
                'Working assessment: #'.
                ($description['working_assessment_id'] ?? 'NULL')
            );

            $this->line(
                '  status: '.
                ($description['working_status'] ?? 'NULL')
            );

            $this->line(
                '  published_at: '.
                ($description['working_published_at'] ?? 'NULL')
            );

            $workingActive =
                $description['working_is_active'];

            $this->line(
                '  is_active: '.
                (
                    $workingActive === null
                        ? 'NULL'
                        : ($workingActive ? 'true' : 'false')
                )
            );

            $this->newLine();

            $this->comment('Cambios propuestos:');

            $this->line(
                '  official.is_active: '.
                (
                    $description['official_is_active']
                        ? 'true'
                        : 'false'
                ).
                ' -> true'
            );

            $this->line(
                '  official.inactivated_at: '.
                ($description['official_inactivated_at'] ?? 'NULL').
                ' -> NULL'
            );

            $this->line(
                '  official.superseded_by_assessment_id: '.
                ($description['official_superseded_by'] ?? 'NULL').
                ' -> NULL'
            );

            $this->line(
                '  working assessment: NO CHANGE'
            );

            if (! $apply) {
                $this->warn(
                    'DRY-RUN · No se modificó la base de datos.'
                );

                continue;
            }

            $result = $service->reconcile($official);

            if ($result['changed'] ?? false) {
                $changed++;

                $this->info(
                    'APPLIED · Assessment #'.
                    $official->id.
                    ' reconciliado.'
                );
            } else {
                $this->warn(
                    'SKIPPED · '.
                    ($result['reason'] ?? 'unknown_reason')
                );
            }
        }

        $this->newLine();
        $this->line(str_repeat('=', 70));
        $this->info('Resumen');
        $this->line(
            'Candidatos: '.$candidates->count()
        );
        $this->line(
            'Cambios aplicados: '.$changed
        );

        if (! $apply) {
            $this->warn(
                'DATABASE CHANGES: 0 · DRY-RUN'
            );
        }

        return self::SUCCESS;
    }
}
