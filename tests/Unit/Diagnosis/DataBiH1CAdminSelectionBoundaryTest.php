<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DataBiH1CAdminSelectionBoundaryTest extends TestCase
{
    #[Test]
    public function lauda_admin_route_is_a_human_action_and_not_a_commercial_write(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminDataBiImplementationCandidateController.php'));
        $this->assertIsString($controller);
        $this->assertStringContainsString("role === 'admin'", $controller); // static safety contract
        $this->assertStringContainsString('->selectChallenge(', $controller);
        $this->assertStringContainsString("'data_transformation_bi'", $controller);
        $this->assertStringNotContainsString('CommercialEngagement::create(', $controller);
        $this->assertStringNotContainsString('ImplementationPlan::create(', $controller);
    }

    #[Test]
    public function tenant_app_has_no_candidate_selection_component(): void
    {
        $tenant = file_get_contents(resource_path('js/pages/App/DataTransformationBi.vue'));
        $this->assertIsString($tenant);
        $this->assertStringNotContainsString('ImplementationCandidatesPanel', $tenant);
        $this->assertStringNotContainsString('/data-bi-candidates', $tenant);
    }

    #[Test]
    public function admin_page_includes_candidate_selection_with_explicit_consent(): void
    {
        $page = file_get_contents(resource_path('js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'));
        $panel = file_get_contents(resource_path('js/pages/Admin/Transformation360/ImplementationRequests/ImplementationCandidatesPanel.vue'));
        $this->assertStringContainsString('<ImplementationCandidatesPanel v-if="props.capability.key === DATA_BI_CAPABILITY" />', $page);
        $this->assertStringContainsString('window.confirm(', $panel);
        $this->assertStringContainsString('Esto no crea un plan, contrato ni inicia trabajos.', $panel);
    }
}
