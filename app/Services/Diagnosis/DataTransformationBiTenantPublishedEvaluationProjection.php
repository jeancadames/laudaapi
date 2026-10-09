<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;
use App\Models\TransformationImplementationRequest;

final class DataTransformationBiTenantPublishedEvaluationProjection
{
    private const KNOWN_DIAGNOSTIC_ANALYSIS_SCHEMA_VERSIONS = [
        2,
        3,
    ];

    /**
     * Return only the latest published professional evaluation
     * belonging to this exact Data BI implementation request.
     *
     * Draft and ready-for-review evaluations are intentionally invisible
     * to the tenant.
     *
     * @return array<string,mixed>|null
     */
    public function forRequest(
        TransformationImplementationRequest $request
    ): ?array {
        if (
            (string) $request->capability_key
                !== 'data_transformation_bi'
            || (int) $request->company_id <= 0
        ) {
            return null;
        }

        $evaluation =
            DataTransformationBiEvaluation::query()
                ->where(
                    'company_id',
                    (int) $request->company_id
                )
                ->where(
                    'transformation_implementation_request_id',
                    (int) $request->getKey()
                )
                ->where(
                    'status',
                    DataTransformationBiEvaluation::STATUS_PUBLISHED
                )
                ->with([
                    'findings.sources',
                ])
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->first();

        return $this->project(
            $evaluation
        );
    }

    /**
     * Published historical results, bounded to an authorized company.
     * Excludes the current implementation request.
     * Uses the existing tenant-safe projection.
     *
     * @return list<array<string,mixed>>
     */
    public function forCompanyHistory(
        int $companyId,
        ?int $currentRequestId
    ): array {
        if ($companyId <= 0 || $currentRequestId === null
            || $currentRequestId <= 0) {
            return [];
        }

        return DataTransformationBiEvaluation::query()
            ->where('company_id', $companyId)
            ->where(
                'status',
                DataTransformationBiEvaluation::STATUS_PUBLISHED
            )
            ->whereNotNull('published_at')
            ->where(
                'transformation_implementation_request_id',
                '!=',
                $currentRequestId
            )
            ->with(['findings.sources'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (DataTransformationBiEvaluation $evaluation) =>
                $this->project($evaluation)
            )
            ->filter(fn ($result) => is_array($result))
            ->values()
            ->all();
    }

    /**
     * Deliberately narrow tenant projection.
     *
     * It must not expose:
     * - evaluation/finding/source IDs;
     * - draft/review lifecycle state;
     * - evidence versions or hashes;
     * - snapshots;
     * - actor IDs;
     * - Admin actions.
     *
     * @return array<string,mixed>|null
     */
    public function project(
        ?DataTransformationBiEvaluation $evaluation
    ): ?array {
        if (
            $evaluation === null
            || ! $evaluation->isPublished()
        ) {
            return null;
        }

        $evaluation->loadMissing([
            'findings.sources',
        ]);

        $findings =
            $evaluation
                ->findings
                ->map(
                    static function (
                        DataTransformationBiEvaluationFinding $finding
                    ): array {
                        $sources =
                            $finding
                                ->sources
                                ->pluck('display_name')
                                ->filter(
                                    static fn ($name): bool =>
                                        is_string($name)
                                        && trim($name) !== ''
                                )
                                ->map(
                                    static fn ($name): string =>
                                        trim(
                                            (string) $name
                                        )
                                )
                                ->unique()
                                ->values()
                                ->all();

                        return [
                            'finding_type' =>
                                (string) $finding->finding_type,

                            'title' =>
                                (string) $finding->title,

                            'details' =>
                                (string) $finding->details,

                            'recommendation' =>
                                $finding->recommendation !== null
                                    ? (string) $finding->recommendation
                                    : null,

                            'priority' =>
                                $finding->priority !== null
                                    ? (string) $finding->priority
                                    : null,

                            'sources' =>
                                $sources,
                        ];
                    }
                )
                ->values();

        return [
            'published_at' =>
                $evaluation
                    ->published_at
                    ?->toISOString(),

            'summary' => [
                'weakness_count' =>
                    $findings
                        ->where(
                            'finding_type',
                            DataTransformationBiEvaluationFinding
                                ::TYPE_WEAKNESS
                        )
                        ->count(),

                'opportunity_count' =>
                    $findings
                        ->where(
                            'finding_type',
                            DataTransformationBiEvaluationFinding
                                ::TYPE_OPPORTUNITY
                        )
                        ->count(),

                'observation_count' =>
                    $findings
                        ->where(
                            'finding_type',
                            DataTransformationBiEvaluationFinding
                                ::TYPE_OBSERVATION
                        )
                        ->count(),
            ],

            // D2D_TENANT_PUBLISHED_DIAGNOSTIC_ANALYSIS
            'diagnostic_analysis' =>
                $this->tenantDiagnosticAnalysis(
                    $evaluation
                ),

            'executive_summary' =>
                $this->tenantExecutiveSummary(
                    $evaluation
                ),

            'findings' =>
                $findings->all(),
        ];
    }

    /**
     * Tenant-safe executive summary from frozen evidence.
     *
     * Never recomputes analysis or exposes source snapshots.
     *
     * @return array<string,mixed>
     */
    private function tenantExecutiveSummary(
        DataTransformationBiEvaluation $evaluation
    ): array {
        $unavailable = [
            'available' => false,
            'source_count' => null,
            'declared_domain_count' => null,
            'analysis_count' => null,
            'supported_count' => null,
            'partial_count' => null,
            'insufficient_evidence_count' => null,
        ];

        $version = (int) (
            $evaluation->diagnostic_analysis_schema_version ?? 0
        );

        if (! in_array(
            $version,
            self::KNOWN_DIAGNOSTIC_ANALYSIS_SCHEMA_VERSIONS,
            true
        )) {
            return $unavailable;
        }

        $snapshot = $evaluation->diagnostic_analysis_snapshot;

        if (
            ! is_array($snapshot)
            || ($snapshot['kind'] ?? null)
                !== 'data_bi_diagnostic_analysis'
            || (int) ($snapshot['schema_version'] ?? 0)
                !== $version
            || ($snapshot['available'] ?? false) !== true
            || ! is_array($snapshot['analyses'] ?? null)
        ) {
            return $unavailable;
        }

        $counts = [
            'supported' => 0,
            'partial' => 0,
            'not_supported_by_current_evidence' => 0,
        ];

        $validAnalysisCount = 0;

        foreach ($snapshot['analyses'] as $analysis) {
            $projected = $this->tenantDiagnosticAnalysisItem(
                $analysis
            );

            if ($projected === null) {
                continue;
            }

            $validAnalysisCount++;

            $status = $projected['status'];

            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }

        $classification = data_get(
            $snapshot,
            'semantic_diagnostic.classification'
        );

        $declaredDomainCount = null;

        if (
            is_array($classification)
            && isset($classification['unique_domain_count'])
            && is_numeric($classification['unique_domain_count'])
        ) {
            $declaredDomainCount = max(
                0,
                (int) $classification['unique_domain_count']
            );
        }

        $sourceCount = null;

        if (
            isset($snapshot['source_count'])
            && is_numeric($snapshot['source_count'])
        ) {
            $sourceCount = max(
                0,
                (int) $snapshot['source_count']
            );
        }

        return [
            'available' => true,
            'source_count' => $sourceCount,
            'declared_domain_count' => $declaredDomainCount,
            'analysis_count' => $validAnalysisCount,
            'supported_count' => $counts['supported'],
            'partial_count' => $counts['partial'],
            'insufficient_evidence_count' =>
                $counts['not_supported_by_current_evidence'],
        ];
    }

    /**
     * Tenant-safe projection of the already frozen diagnostic analysis.
     *
     * D2D rules:
     * - published evaluation snapshot only;
     * - known frozen schemas V2 and V3 only;
     * - never recompute from live sources;
     * - never expose hashes, internal ids, status_basis or source ids.
     *
     * @return array{
     *     available: bool,
     *     analyses: list<array<string,mixed>>
     * }
     */
    private function tenantDiagnosticAnalysis(
        DataTransformationBiEvaluation $evaluation
    ): array {
        $unavailable = [
            'available' => false,
            'analyses' => [],
        ];

        $schemaVersion =
            (int) (
                $evaluation
                    ->diagnostic_analysis_schema_version
                ?? 0
            );

        if (
            ! in_array(
                $schemaVersion,
                self::KNOWN_DIAGNOSTIC_ANALYSIS_SCHEMA_VERSIONS,
                true
            )
        ) {
            return $unavailable;
        }

        $snapshot =
            $evaluation
                ->diagnostic_analysis_snapshot;

        if (! is_array($snapshot)) {
            return $unavailable;
        }

        $snapshotSchemaVersion =
            (int) (
                $snapshot['schema_version']
                ?? 0
            );

        if (
            ($snapshot['kind'] ?? null)
                !== 'data_bi_diagnostic_analysis'
            || ! in_array(
                $snapshotSchemaVersion,
                self::KNOWN_DIAGNOSTIC_ANALYSIS_SCHEMA_VERSIONS,
                true
            )
            || $snapshotSchemaVersion
                !== $schemaVersion
            || ($snapshot['available'] ?? false)
                !== true
            || ! is_array(
                $snapshot['analyses']
                ?? null
            )
        ) {
            return $unavailable;
        }

        $analyses = [];

        foreach (
            $snapshot['analyses']
            as $analysis
        ) {
            $projected =
                $this
                    ->tenantDiagnosticAnalysisItem(
                        $analysis
                    );

            if ($projected !== null) {
                $analyses[] = $projected;
            }
        }

        return [
            'available' => true,
            'analyses' => $analyses,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function tenantDiagnosticAnalysisItem(
        mixed $analysis
    ): ?array {
        if (! is_array($analysis)) {
            return null;
        }

        $key =
            trim(
                (string) (
                    $analysis['key']
                    ?? ''
                )
            );

        $label =
            trim(
                (string) (
                    $analysis['label']
                    ?? ''
                )
            );

        $status =
            trim(
                (string) (
                    $analysis['status']
                    ?? ''
                )
            );

        if (
            $key === ''
            || $label === ''
            || ! in_array(
                $status,
                [
                    'supported',
                    'partial',
                    'not_supported_by_current_evidence',
                ],
                true
            )
        ) {
            return null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,

            'required_signal_keys' =>
                $this->tenantStringList(
                    $analysis[
                        'required_signal_keys'
                    ]
                    ?? []
                ),

            'observed_signal_keys' =>
                $this->tenantStringList(
                    $analysis[
                        'observed_signal_keys'
                    ]
                    ?? []
                ),

            'supporting_source_count' =>
                max(
                    0,
                    (int) (
                        $analysis[
                            'supporting_source_count'
                        ]
                        ?? 0
                    )
                ),

            'evidence_column_count' =>
                max(
                    0,
                    (int) (
                        $analysis[
                            'evidence_column_count'
                        ]
                        ?? 0
                    )
                ),

            'coverage_counts' =>
                $this->tenantNumericMap(
                    $analysis[
                        'coverage_counts'
                    ]
                    ?? []
                ),

            'observed_type_families' =>
                $this->tenantStringList(
                    $analysis[
                        'observed_type_families'
                    ]
                    ?? []
                ),

            'declared_domain_context' =>
                $this->tenantDomainList(
                    $analysis[
                        'declared_domain_context'
                    ]
                    ?? []
                ),

            'supporting_evidence' =>
                $this->tenantSupportingEvidenceList(
                    $analysis[
                        'supporting_evidence'
                    ]
                    ?? []
                ),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function tenantSupportingEvidenceList(
        mixed $evidence
    ): array {
        if (! is_iterable($evidence)) {
            return [];
        }

        $result = [];

        foreach ($evidence as $item) {
            if (! is_array($item)) {
                continue;
            }

            $result[] = [
                'display_name' =>
                    $this->tenantNullableString(
                        $item['display_name']
                        ?? null
                    ),

                'declared_domains' =>
                    $this->tenantDomainList(
                        $item[
                            'declared_domains'
                        ]
                        ?? []
                    ),

                'matched_column_count' =>
                    max(
                        0,
                        (int) (
                            $item[
                                'matched_column_count'
                            ]
                            ?? 0
                        )
                    ),

                'coverage_counts' =>
                    $this->tenantNumericMap(
                        $item[
                            'coverage_counts'
                        ]
                        ?? []
                    ),

                'columns' =>
                    $this->tenantDiagnosticColumnList(
                        $item['columns']
                        ?? []
                    ),
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function tenantDiagnosticColumnList(
        mixed $columns
    ): array {
        if (! is_iterable($columns)) {
            return [];
        }

        $result = [];

        foreach ($columns as $column) {
            if (! is_array($column)) {
                continue;
            }

            $result[] = [
                'sheet_index' =>
                    isset($column['sheet_index'])
                        ? (int) $column['sheet_index']
                        : null,

                'sheet_name' =>
                    $this->tenantNullableString(
                        $column['sheet_name']
                        ?? null
                    ),

                'column_key' =>
                    $this->tenantNullableString(
                        $column['column_key']
                        ?? null
                    ),

                'column_index' =>
                    isset($column['column_index'])
                        ? (int) $column['column_index']
                        : null,

                'header' =>
                    $this->tenantNullableString(
                        $column['header']
                        ?? null
                    ),

                'matched_term' =>
                    $this->tenantNullableString(
                        $column['matched_term']
                        ?? null
                    ),

                'coverage_status' =>
                    $this->tenantNullableString(
                        $column[
                            'coverage_status'
                        ]
                        ?? null
                    ),

                'observed_type_families' =>
                    $this->tenantStringList(
                        $column[
                            'observed_type_families'
                        ]
                        ?? []
                    ),
            ];
        }

        return $result;
    }

    /**
     * @return list<array{domain:string,group:?string}>
     */
    private function tenantDomainList(
        mixed $domains
    ): array {
        if (! is_iterable($domains)) {
            return [];
        }

        $result = [];

        foreach ($domains as $domain) {
            if (! is_array($domain)) {
                continue;
            }

            $name =
                trim(
                    (string) (
                        $domain['domain']
                        ?? ''
                    )
                );

            if ($name === '') {
                continue;
            }

            $result[] = [
                'domain' => $name,
                'group' =>
                    $this->tenantNullableString(
                        $domain['group']
                        ?? null
                    ),
            ];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function tenantStringList(
        mixed $values
    ): array {
        if (! is_iterable($values)) {
            return [];
        }

        $result = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $normalized =
                trim((string) $value);

            if ($normalized === '') {
                continue;
            }

            $result[] = $normalized;
        }

        return array_values(
            array_unique($result)
        );
    }

    /**
     * @return array<string,int>
     */
    private function tenantNumericMap(
        mixed $values
    ): array {
        if (! is_array($values)) {
            return [];
        }

        $result = [];

        foreach (
            $values
            as $key => $value
        ) {
            if (
                ! is_string($key)
                || $key === ''
                || ! is_numeric($value)
            ) {
                continue;
            }

            $result[$key] =
                max(
                    0,
                    (int) $value
                );
        }

        return $result;
    }

    private function tenantNullableString(
        mixed $value
    ): ?string {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized =
            trim((string) $value);

        return $normalized !== ''
            ? $normalized
            : null;
    }

}
