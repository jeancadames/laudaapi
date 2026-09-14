<?php

test(
    'post staging processing exposes separate profile and normalize routes',
    function () {
        $routes =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/routes/admin.php'
            );

        expect($routes)
            ->toContain(
                '/standard-intake/profile'
            )
            ->toContain(
                'profileStandardIntakeBatch'
            )
            ->toContain(
                'standard_intake.profile'
            )
            ->toContain(
                '/standard-intake/normalize'
            )
            ->toContain(
                'normalizeStandardIntakeProcessingRun'
            )
            ->toContain(
                'standard_intake.normalize'
            );
    }
);

test(
    'profile action is admin capability company and request scoped',
    function () {
        $controller =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        expect($controller)
            ->toContain(
                'profileStandardIntakeBatch('
            )
            ->toContain(
                'DataTransformationBiStagingProfilingService'
            )
            ->toContain(
                "'batch_id'"
            )
            ->toContain(
                "'company_id'"
            )
            ->toContain(
                "'transformation_implementation_request_id'"
            )
            ->toContain(
                "=== 'data_transformation_bi'"
            )
            ->toContain(
                '$this->authorizeAdmin('
            );
    }
);

test(
    'normalize action is explicit and processing run scoped',
    function () {
        $controller =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        expect($controller)
            ->toContain(
                'normalizeStandardIntakeProcessingRun('
            )
            ->toContain(
                'DataTransformationBiStagingNormalizationService'
            )
            ->toContain(
                "'processing_run_id'"
            )
            ->toContain(
                'blocking_issue_count'
            )
            ->toContain(
                'La normalización está bloqueada'
            );
    }
);

test(
    'post staging http surface does not mutate request definition or commercial state',
    function () {
        $controllerPath =
            dirname(__DIR__, 3)
            .'/app/Http/Controllers/Admin/'
            .'AdminTransformationImplementationRequestController.php';

        $controller =
            file_get_contents(
                $controllerPath
            );

        $profileStart =
            strpos(
                $controller,
                'public function profileStandardIntakeBatch('
            );

        $showStart =
            strpos(
                $controller,
                'public function show(',
                $profileStart
            );

        expect($profileStart)
            ->not
            ->toBeFalse()
            ->and($showStart)
            ->not
            ->toBeFalse();

        $surface =
            substr(
                $controller,
                $profileStart,
                $showStart - $profileStart
            );

        expect($surface)
            ->not
            ->toContain(
                '->transition('
            )
            ->not
            ->toContain(
                'Definition::update'
            )
            ->not
            ->toContain(
                'Subscription'
            )
            ->not
            ->toContain(
                'Activation'
            )
            ->not
            ->toContain(
                'ready_for_commercial'
            );
    }
);

test(
    'normalization remains a separate action after profiling',
    function () {
        $controller =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        $profileStart =
            strpos(
                $controller,
                'public function profileStandardIntakeBatch('
            );

        $normalizeStart =
            strpos(
                $controller,
                'public function normalizeStandardIntakeProcessingRun('
            );

        $profileSurface =
            substr(
                $controller,
                $profileStart,
                $normalizeStart - $profileStart
            );

        expect($profileSurface)
            ->toContain(
                '$profilingService->profile('
            )
            ->not
            ->toContain(
                '$normalizationService->normalize('
            );
    }
);
