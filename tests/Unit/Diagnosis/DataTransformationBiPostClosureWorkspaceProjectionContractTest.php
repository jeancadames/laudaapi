<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataTransformationBiIntakeV2StateService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * BI17 FIX3: regression for server-owned state and consultation UI.
 * Pure unit checks do not connect to the database.
 */
final class DataTransformationBiPostClosureWorkspaceProjectionContractTest extends TestCase
{
    private function actionPermissions(?array $session, bool $closed): array
    {
        $reflection = new ReflectionClass(DataTransformationBiIntakeV2StateService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('actions');

        return $method->invoke($service, $session, $closed);
    }

    public function test_closure_denies_every_action_with_or_without_session(): void
    {
        foreach ([null, ['status' => 'draft'], ['status' => 'ready'], ['status' => 'submitted_for_evaluation']] as $session) {
            $actions = $this->actionPermissions($session, true);

            self::assertNotEmpty($actions);
            foreach ($actions as $permission) {
                self::assertFalse($permission);
            }
        }
    }

    public function test_open_session_keeps_existing_permission_semantics(): void
    {
        $withoutSession = $this->actionPermissions(null, false);
        self::assertTrue($withoutSession['can_start_or_resume']);
        self::assertTrue($withoutSession['can_start_new_session']);

        $draft = $this->actionPermissions(['status' => 'draft'], false);
        self::assertTrue($draft['can_manage_sources']);
        self::assertTrue($draft['can_submit_for_evaluation']);

        $ready = $this->actionPermissions(['status' => 'ready'], false);
        self::assertTrue($ready['can_manage_sources']);
        self::assertFalse($ready['can_submit_for_evaluation']);
    }

    public function test_tenant_projection_exposes_only_read_only_closure_state(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = file_get_contents($root.'/app/Http/Controllers/AppHubDataTransformationBiController.php');
        $vue = file_get_contents($root.'/resources/js/pages/App/DataTransformationBi.vue');
        $service = file_get_contents($root.'/app/Services/Diagnosis/DataTransformationBiIntakeV2StateService.php');

        self::assertIsString($controller);
        self::assertIsString($vue);
        self::assertIsString($service);
        self::assertStringContainsString("'functionally_closed' =>", $controller);
        self::assertStringContainsString("'functionally_closed' =>", $service);
        self::assertStringContainsString('functionally_closed: boolean', $vue);
        self::assertStringContainsString('Entrega técnica cerrada · Solo consulta', $vue);
        self::assertStringContainsString('v-if="source_workspace.access.can_manage"', $vue);
        self::assertStringContainsString('v-if="source_workspace.functionally_closed"', $vue);
        self::assertStringContainsString('v-else-if="source_workspace.session"', $vue);
    }

    public function test_sql_server_preview_is_a_nonpersistent_query_generator(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = file_get_contents($root.'/app/Http/Controllers/AppHubDataTransformationBiSourceWorkspaceController.php');
        $assistant = file_get_contents($root.'/app/Services/Diagnosis/DataTransformationBiSqlServerExtractionAssistant.php');

        self::assertIsString($controller);
        self::assertIsString($assistant);
        $start = strpos($controller, 'public function previewSourceAssetSqlServerExtraction(');
        self::assertNotFalse($start);
        $end = strpos($controller, 'return $this->tenantJson([', $start);
        self::assertNotFalse($end);
        $body = substr($controller, $start, $end - $start);

        self::assertStringContainsString('$assistant->preview(', $body);
        foreach (['->save(', '->create(', '->update(', '->delete(', 'DB::', 'Storage::'] as $writingPattern) {
            self::assertStringNotContainsString($writingPattern, $body);
        }
        self::assertStringContainsString('$this->buildSelect(', $assistant);
    }
}
