<?php

test(
    'd15 f state exposes bounded safe feedback instead of raw snapshots',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiIntakeV2StateService.php'
            );

        expect($source)
            ->toContain(
                'D15F_SAFE_ERROR_PROJECTION'
            )
            ->toContain(
                "'validation_feedback'"
            )
            ->toContain(
                'private function validationFeedback('
            )
            ->toContain(
                'private function safeMessages('
            )
            ->toContain(
                'count($safe) >= 20'
            )
            ->toContain(
                'strlen($message) > 1000'
            );
    }
);

test(
    'd15 f frontend scopes operational failures to the affected domain',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/'
                .'Show.vue'
            );

        expect($source)
            ->toContain(
                'D15F_ERROR_FEEDBACK_TYPES'
            )
            ->toContain(
                'D15F_ERROR_FEEDBACK_LOGIC'
            )
            ->toContain(
                'standardIntakeV2DomainErrors'
            )
            ->toContain(
                'standardIntakeV2SetDomainError('
            )
            ->toContain(
                'standardIntakeV2ClearDomainError('
            )
            ->toContain(
                'standardIntakeV2DomainErrorsFor('
            )
            ->toContain(
                'standardIntakeV2DomainWarningsFor('
            );
    }
);

test(
    'd15 f patches upload no data and carry forward actions',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/'
                .'Show.vue'
            );

        foreach ([
            'uploadStandardIntakeV2Domain',
            'noDataStandardIntakeV2Domain',
            'carryForwardStandardIntakeV2Domain',
        ] as $function) {
            $start =
                strpos(
                    $source,
                    "async function {$function}("
                );

            expect($start)
                ->not
                ->toBeFalse();

            $next =
                strpos(
                    $source,
                    "\nasync function ",
                    $start + 10
                );

            $block =
                $next === false
                    ? substr(
                        $source,
                        $start
                    )
                    : substr(
                        $source,
                        $start,
                        $next - $start
                    );

            expect($block)
                ->toContain(
                    'standardIntakeV2ClearDomainError('
                )
                ->toContain(
                    'standardIntakeV2SetDomainError('
                );
        }
    }
);

test(
    'd15 f renders relational and domain correction feedback',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/'
                .'Show.vue'
            );

        expect($source)
            ->toContain(
                'D15F_RELATIONAL_HTTP_FEEDBACK'
            )
            ->toContain(
                'D15F_RELATIONAL_FEEDBACK_UI'
            )
            ->toContain(
                'D15F_DOMAIN_FEEDBACK_UI'
            )
            ->toContain(
                'standardIntakeV2RelationErrors()'
            )
            ->toContain(
                'standardIntakeV2RelationWarnings()'
            )
            ->toContain(
                'Hay relaciones entre dominios que requieren corrección.'
            )
            ->toContain(
                'Requiere corrección'
            )
            ->toContain(
                'Advertencias'
            );
    }
);

test(
    'd15 f keeps profiling and normalization manual',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/'
                .'Show.vue'
            );

        $start =
            strpos(
                $source,
                'async function resolveStandardIntakeV2Session('
            );

        $end =
            strpos(
                $source,
                'async function materializeStandardIntakeV2Session(',
                $start
            );

        expect($start)
            ->not
            ->toBeFalse();

        expect($end)
            ->not
            ->toBeFalse();

        $block =
            substr(
                $source,
                $start,
                $end - $start
            );

        expect($block)
            ->not
            ->toContain(
                'profileStandardIntakeBatch()'
            )
            ->not
            ->toContain(
                'normalizeStandardIntakeProcessingRun()'
            )
            ->not
            ->toContain(
                'normalizeStandardIntakeBatch()'
            );
    }
);

test(
    'd15 f frontend keeps private storage metadata hidden',
    function () {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/'
                .'Show.vue'
            );

        foreach ([
            'source_path',
            'source_disk',
            'validation_snapshot',
        ] as $forbidden) {
            expect($source)
                ->not
                ->toContain(
                    $forbidden
                );
        }

        /*
         * SHA-256 is intentionally safe artifact metadata.
         * It supports integrity without exposing private storage.
         */
        expect($source)
            ->toContain(
                'source_sha256'
            )
            ->toContain(
                'SHA-256'
            );
    }
);
