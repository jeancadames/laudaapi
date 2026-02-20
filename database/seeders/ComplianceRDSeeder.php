<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComplianceRDSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // -----------------------------
            // Authorities
            // -----------------------------
            $dgiiId = $this->upsertAuthority('DO', 'DGII', 'Dirección General de Impuestos Internos', 'https://dgii.gov.do');
            $tssId  = $this->upsertAuthority('DO', 'TSS',  'Tesorería de la Seguridad Social', 'https://tss.gob.do');

            // -----------------------------
            // Templates (DGII)
            // -----------------------------
            $dgiiDefaults = [
                'country_code' => 'DO',
                'active' => true,
                'version' => 1,
                'effective_from' => null,
                'effective_to' => null,
                'default_reminders' => [7, 3, 1, 0],
            ];

            $it1Id = $this->upsertTemplate($dgiiId, array_merge($dgiiDefaults, [
                'code' => 'IT-1',
                'name' => 'ITBIS (IT-1)',
                'frequency' => 'monthly',
                'due_rule' => [
                    'type' => 'monthly_day',
                    'day' => 20,
                    'month_offset' => 1,
                    'shift' => 'company_default', // weekend shift
                ],
                'official_ref_url' => 'https://dgii.gov.do/cicloContribuyente/obligacionesTributarias/declaracionPagoImpuestos/Paginas/PagodeImpuesto.aspx',
            ]));

            $ir3Id = $this->upsertTemplate($dgiiId, array_merge($dgiiDefaults, [
                'code' => 'IR-3',
                'name' => 'Retenciones Asalariados (IR-3)',
                'frequency' => 'monthly',
                'due_rule' => [
                    'type' => 'monthly_day',
                    'day' => 10,
                    'month_offset' => 1,
                    'shift' => 'company_default',
                ],
                'official_ref_url' => 'https://dgii.gov.do/cicloContribuyente/obligacionesTributarias/declaracionPagoImpuestos/Paginas/PagodeImpuesto.aspx',
            ]));

            $ir17Id = $this->upsertTemplate($dgiiId, array_merge($dgiiDefaults, [
                'code' => 'IR-17',
                'name' => 'Otras Retenciones y Retribuciones (IR-17)',
                'frequency' => 'monthly',
                'due_rule' => [
                    'type' => 'monthly_day',
                    'day' => 10,
                    'month_offset' => 1,
                    'shift' => 'company_default',
                ],
                'official_ref_url' => 'https://dgii.gov.do/cicloContribuyente/obligacionesTributarias/declaracionPagoImpuestos/Paginas/PagodeImpuesto.aspx',
            ]));

            $f606Id = $this->upsertTemplate($dgiiId, array_merge($dgiiDefaults, [
                'code' => '606',
                'name' => 'Formato Compras (606)',
                'frequency' => 'monthly',
                'due_rule' => [
                    'type' => 'monthly_day',
                    'day' => 15,
                    'month_offset' => 1,
                    'shift' => 'company_default',
                ],
                'official_ref_url' => 'https://dgii.gov.do/cicloContribuyente/obligacionesTributarias/declaracionPagoImpuestos/Paginas/PagodeImpuesto.aspx',
            ]));

            $f607Id = $this->upsertTemplate($dgiiId, array_merge($dgiiDefaults, [
                'code' => '607',
                'name' => 'Formato Ventas (607)',
                'frequency' => 'monthly',
                'due_rule' => [
                    'type' => 'monthly_day',
                    'day' => 15,
                    'month_offset' => 1,
                    'shift' => 'company_default',
                ],
                'official_ref_url' => 'https://dgii.gov.do/cicloContribuyente/obligacionesTributarias/declaracionPagoImpuestos/Paginas/PagodeImpuesto.aspx',
            ]));

            // -----------------------------
            // Template (TSS) + overrides calendar 2026
            // -----------------------------
            $tssTemplateId = $this->upsertTemplate($tssId, [
                'country_code' => 'DO',
                'code' => 'TSS-SDSS',
                'name' => 'TSS (Pago SDSS sin recargo)',
                'description' => 'Fecha límite de pago sin recargo según calendario anual TSS.',
                'frequency' => 'monthly',
                'due_rule' => [
                    'type' => 'year_table',
                    'source' => 'tss_calendar',
                    'fallback' => [
                        'type' => 'monthly_nth_business_day',
                        'n' => 3,
                        'month_offset' => 1,
                        'shift' => 'none',
                    ],
                ],
                'default_reminders' => [7, 3, 1, 0],
                'version' => 1,
                'active' => true,
                'official_ref_url' => 'https://tss.gob.do/consultas/calendario-de-pagos/',
                'meta' => [
                    'note' => 'Overrides seed for year 2026 (period_key payroll month => due_date next month).',
                ],
            ]);

            // Overrides 2026 (period_key = mes de contribución)
            // Fuente: Calendario de pagos sin recargo SDSS 2026 (TSS) :contentReference[oaicite:3]{index=3}
            $overrides = [
                '2025-12' => '2026-01-07',
                '2026-01' => '2026-02-04',
                '2026-02' => '2026-03-04',
                '2026-03' => '2026-04-06',
                '2026-04' => '2026-05-06',
                '2026-05' => '2026-06-03',
                '2026-06' => '2026-07-03',
                '2026-07' => '2026-08-05',
                '2026-08' => '2026-09-03',
                '2026-09' => '2026-10-05',
                '2026-10' => '2026-11-04',
                '2026-11' => '2026-12-03',
            ];

            foreach ($overrides as $periodKey => $dueDate) {
                DB::table('compliance_due_overrides')->updateOrInsert(
                    ['template_id' => $tssTemplateId, 'period_key' => $periodKey],
                    [
                        'due_date' => $dueDate,
                        'source' => 'tss',
                        'meta' => json_encode(['year' => 2026], JSON_UNESCAPED_SLASHES),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });
    }

    private function upsertAuthority(string $country, string $code, string $name, ?string $website): int
    {
        DB::table('tax_authorities')->updateOrInsert(
            ['country_code' => $country, 'code' => $code],
            [
                'name' => $name,
                'website' => $website,
                'active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (int) DB::table('tax_authorities')
            ->where('country_code', $country)
            ->where('code', $code)
            ->value('id');
    }

    private function upsertTemplate(int $authorityId, array $data): int
    {
        $key = [
            'authority_id' => $authorityId,
            'code' => $data['code'],
            'version' => $data['version'] ?? 1,
        ];

        $payload = array_merge([
            'country_code' => 'DO',
            'name' => $data['code'],
            'description' => null,
            'frequency' => 'monthly',
            'due_rule' => [],
            'applicability_rule' => null,
            'default_reminders' => null,
            'effective_from' => null,
            'effective_to' => null,
            'active' => true,
            'official_ref_url' => null,
            'meta' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ], $data);

        // JSON fields:
        $payload['due_rule'] = json_encode($payload['due_rule'], JSON_UNESCAPED_SLASHES);
        if (is_array($payload['default_reminders'])) {
            $payload['default_reminders'] = json_encode($payload['default_reminders']);
        }
        if (is_array($payload['applicability_rule'])) {
            $payload['applicability_rule'] = json_encode($payload['applicability_rule'], JSON_UNESCAPED_SLASHES);
        }
        if (is_array($payload['meta'])) {
            $payload['meta'] = json_encode($payload['meta'], JSON_UNESCAPED_SLASHES);
        }

        DB::table('compliance_obligation_templates')->updateOrInsert($key, $payload);

        return (int) DB::table('compliance_obligation_templates')
            ->where($key)
            ->value('id');
    }
}
