<?php

namespace Tests\Unit\Diagnosis;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class DataTransformationBiFunctionalProposalPreviewContractTest extends TestCase
{
    public function test_preview_is_a_separate_get_action(): void
    {
        $name = 'admin.transformation360.implementation_requests.definition.functional_proposals.preview';
        $this->assertTrue(Route::has($name));
        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route);
        $this->assertContains('GET', $route->methods());
        $this->assertStringContainsString(
            'previewFunctionalProposals',
            $route->getActionName()
        );
    }

    public function test_preview_uses_persisted_server_data_and_auth_guard(): void
    {
        $path = app_path('Http/Controllers/Admin/AdminTransformationImplementationRequestDefinitionActionController.php');
        $code = file_get_contents($path);
        $start = strpos($code, 'public function previewFunctionalProposals(');
        $end = strpos($code, 'public function review(', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $method = substr($code, $start, $end - $start);
        $this->assertStringContainsString('$this->authorizeAdmin($request)', $method);
        $this->assertStringContainsString('$this->assertDefinitionContext(', $method);
        $this->assertStringContainsString('$implementationRequest->source_snapshot', $method);
        $this->assertStringContainsString('$definition->implementation_scope', $method);
        $this->assertStringContainsString('$definition->deliverables', $method);
        $this->assertStringContainsString('no-store, private', $method);
        $this->assertStringNotContainsString('DB::transaction', $method);
    }

    public function test_client_requires_explicit_form_save_after_application(): void
    {
        $code = file_get_contents(resource_path('js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'));
        $this->assertStringContainsString('dataBiApplyFunctionalProposals', $code);
        $this->assertStringContainsString('dataBiPlanChanged();', $code);
        $this->assertStringContainsString('Guardar revisión humana', $code);
        $this->assertStringContainsString('dataBiProposalBaselineSignature', $code);
    }
}
