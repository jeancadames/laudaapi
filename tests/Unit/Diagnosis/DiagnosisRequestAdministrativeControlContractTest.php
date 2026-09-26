<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DiagnosisRequestAdministrativeControlContractTest extends TestCase
{
    private function source(string $file): string
    {
        return file_get_contents(
            dirname(__DIR__, 3).'/'.ltrim($file, '/')
        ) ?: '';
    }

    public function test_request_has_explicit_inactive_lifecycle(): void
    {
        $model = $this->source(
            'app/Models/DiagnosisAccessRequest.php'
        );

        foreach ([
            "STATUS_INACTIVE = 'inactive'",
            "'inactivated_at'",
            "'inactivated_by_user_id'",
            "'inactivation_reason'",
            "'status_before_inactivation'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $model
            );
        }
    }

    public function test_company_has_diagnosis_request_control(): void
    {
        $migration = $this->source(
            'database/migrations/'
            .'2026_09_26_170000_add_diagnosis_request_administrative_controls.php'
        );

        foreach ([
            "'company_diagnosis_settings'",
            "'new_requests_blocked'",
            "'blocked_at'",
            "'blocked_by_user_id'",
            "'block_reason'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $migration
            );
        }

        $company = $this->source(
            'app/Models/Company.php'
        );

        $this->assertStringContainsString(
            'public function diagnosisSetting(): HasOne',
            $company
        );
    }

    public function test_backend_blocks_new_request_creation(): void
    {
        $service = $this->source(
            'app/Services/Diagnosis/'
            .'InitialDiagnosisCommercialService.php'
        );

        foreach ([
            'CompanyDiagnosisSetting::query()',
            'new_requests_blocked',
            'ValidationException::withMessages',
            "'request_blocked' =>",
            "'request_block_reason' =>",
            '&& ! $requestBlocked',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_inactive_request_is_not_pending_or_working(): void
    {
        $service = $this->source(
            'app/Services/Diagnosis/'
            .'InitialDiagnosisCommercialService.php'
        );

        $this->assertGreaterThanOrEqual(
            3,
            substr_count(
                $service,
                'DiagnosisAccessRequest::STATUS_INACTIVE'
            )
        );
    }

    public function test_admin_can_reverse_request_and_company_controls(): void
    {
        $controller = $this->source(
            'app/Http/Controllers/Admin/'
            .'AdminDiagnosisAccessRequestController.php'
        );

        foreach ([
            'public function inactivateRequest(',
            'public function reactivateRequest(',
            'public function blockNewDiagnosisRequests(',
            'public function unblockNewDiagnosisRequests(',
            'diagnosis_access_request_inactivated',
            'diagnosis_access_request_reactivated',
            'diagnosis_new_requests_blocked',
            'diagnosis_new_requests_unblocked',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $controller
            );
        }

        $routes = $this->source(
            'routes/admin.php'
        );

        foreach ([
            '/request/inactivate',
            '/request/reactivate',
            '/company/block-new-requests',
            '/company/unblock-new-requests',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $routes
            );
        }
    }
}
