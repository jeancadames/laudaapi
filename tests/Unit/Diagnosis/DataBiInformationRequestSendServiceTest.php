<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiInformationRequestSendService;
use PHPUnit\Framework\TestCase;

final class DataBiInformationRequestSendServiceTest extends TestCase
{
    public function test_eligible_bi_request_states(): void
    {
        foreach (
            [
                'requested',
                'under_lauda_review',
                'definition_preparation',
            ] as $status
        ) {
            self::assertTrue(
                DataBiInformationRequestSendService::eligible(
                    'data_transformation_bi',
                    $status,
                    false
                )
            );
        }
    }

    public function test_legacy_definition_is_blocked(): void
    {
        foreach (
            [
                'requested',
                'under_lauda_review',
                'definition_preparation',
            ] as $status
        ) {
            self::assertFalse(
                DataBiInformationRequestSendService::eligible(
                    'data_transformation_bi',
                    $status,
                    true
                )
            );
        }
    }

    public function test_other_capabilities_are_rejected(): void
    {
        self::assertFalse(
            DataBiInformationRequestSendService::eligible(
                'other',
                'requested',
                false
            )
        );
    }

    public function test_closed_or_invalid_states_are_rejected(): void
    {
        foreach (
            [
                'definition_agreed',
                'ready_for_commercial',
                'closed',
                'sent',
                '',
            ] as $status
        ) {
            self::assertFalse(
                DataBiInformationRequestSendService::eligible(
                    'data_transformation_bi',
                    $status,
                    false
                )
            );
        }
    }
}
