<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDynamicSourceAsyncProfilingUiContractTest
    extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $source =
            file_get_contents(
                $root
                .'/resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/Show.vue'
            );

        self::assertIsString(
            $source
        );

        $this->source =
            $source;
    }

    public function test_ui_tracks_async_profile_status(): void
    {
        foreach (
            [
                'profiling_status?',
                "'queued'",
                "'processing'",
                "'completed'",
                "'failed'",
                'profiling_queued_at?',
                'profiling_started_at?',
                'profiling_finished_at?',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_ui_polls_profile_status_endpoint(): void
    {
        self::assertStringContainsString(
            'pollDynamicSourceAssetProfiling',
            $this->source
        );

        self::assertStringContainsString(
            '/profile-status',
            $this->source
        );

        self::assertStringContainsString(
            "method: 'GET'",
            $this->source
        );

        self::assertStringContainsString(
            'DYNAMIC_SOURCE_PROFILE_POLL_INTERVAL_MS',
            $this->source
        );

        self::assertStringContainsString(
            'DYNAMIC_SOURCE_PROFILE_POLL_ATTEMPTS',
            $this->source
        );
    }

    public function test_profile_post_no_longer_expects_immediate_completion(): void
    {
        self::assertStringContainsString(
            "method: 'POST'",
            $this->source
        );

        self::assertStringContainsString(
            "'La fuente no quedó encolada para profiling técnico.'",
            $this->source
        );

        self::assertStringContainsString(
            'await pollDynamicSourceAssetProfiling(',
            $this->source
        );
    }

    public function test_button_stays_disabled_while_profile_runs(): void
    {
        self::assertStringContainsString(
            'dynamicSourceProfilingRunning(',
            $this->source
        );

        self::assertStringContainsString(
            "? 'Perfilando...'",
            $this->source
        );
    }

    public function test_ui_surfaces_server_side_failure_message(): void
    {
        self::assertStringContainsString(
            'current.failure_message',
            $this->source
        );

        self::assertStringContainsString(
            "current.profiling_status === 'failed'",
            $this->source
        );
    }

    public function test_ui_resumes_running_profile_after_page_initialization(): void
    {
        foreach (
            [
                'watchDynamicProfile(',
                'immediate: true',
                'resumeDynamicSourceAssetProfiling',
                'dynamicSourceProfilePollers',
                'dynamicSourceProfilePollers.has(',
                'runDynamicSourceAssetProfilingPoll',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_ui_stops_profile_polling_when_component_unmounts(): void
    {
        self::assertStringContainsString(
            'onBeforeUnmountDynamicProfile(',
            $this->source
        );

        self::assertStringContainsString(
            'dynamicSourceProfilePollingStopped',
            $this->source
        );

        self::assertStringContainsString(
            'dynamicSourceProfilePollers.clear()',
            $this->source
        );
    }


    public function test_ui_preserves_data_file_during_async_status_updates(): void
    {
        self::assertStringContainsString(
            'asset.data_file',
            $this->source
        );

        self::assertStringContainsString(
            'item.data_file',
            $this->source
        );
    }

    public function test_ui_polling_horizon_covers_dedicated_worker_timeout(): void
    {
        self::assertStringContainsString(
            'DYNAMIC_SOURCE_PROFILE_POLL_INTERVAL_MS =',
            $this->source
        );

        self::assertStringContainsString(
            '3000',
            $this->source
        );

        self::assertStringContainsString(
            'DYNAMIC_SOURCE_PROFILE_POLL_ATTEMPTS =',
            $this->source
        );

        self::assertStringContainsString(
            '320',
            $this->source
        );
    }

}
