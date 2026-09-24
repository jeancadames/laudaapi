<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDynamicCanonicalRegistryHttpContractTest
    extends TestCase
{
    private string $controller;
    private string $routes;
    private string $service;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->controller =
            file_get_contents(
                $root
                .'/app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiCanonicalModelController.php'
            );

        $this->routes =
            file_get_contents(
                $root
                .'/routes/admin.php'
            );

        $this->service =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalModelService.php'
            );

        self::assertIsString(
            $this->controller
        );

        self::assertIsString(
            $this->routes
        );

        self::assertIsString(
            $this->service
        );
    }

    public function test_admin_exposes_complete_canonical_model_http_contract(): void
    {
        foreach (
            [
                '/canonical-model',
                '/canonical-model/prepare',
                '/canonical-model/registries/{registryId}/entities',
                '/entities/{entityId}/fields',
                '/registries/{registryId}/relationships',
                '/registries/{registryId}/publish',

                "'workspace'",
                "'prepareDraft'",
                "'createEntity'",
                "'replaceFields'",
                "'replaceRelationships'",
                "'publish'",

                'canonical_model.workspace',
                'canonical_model.prepare',
                'canonical_model.entities.create',
                'canonical_model.entities.fields.replace',
                'canonical_model.relationships.replace',
                'canonical_model.publish',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->routes
            );
        }

        self::assertStringContainsString(
            "->whereNumber('registryId')",
            $this->routes
        );

        self::assertStringContainsString(
            "->whereNumber('entityId')",
            $this->routes
        );
    }

    public function test_workspace_get_is_strictly_read_only(): void
    {
        $action =
            $this->methodBlock(
                $this->controller,
                'public function workspace(',
                'public function prepareDraft('
            );

        foreach (
            [
                '$this->actor(',
                '$this->assertRequest(',
                '$this->workspaceResponse(',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $action
            );
        }

        $responseHelper =
            $this->methodBlock(
                $this->controller,
                'private function workspaceResponse(',
                'private function validationError('
            );

        self::assertStringContainsString(
            '$service->workspace(',
            $responseHelper
        );

        foreach (
            [
                'prepareDraft(',
                'createEntity(',
                'replaceFields(',
                'replaceRelationships(',
                'publish(',
                '->create(',
                '->save(',
                'DB::transaction(',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $action
            );
        }

        $serviceWorkspace =
            $this->methodBlock(
                $this->service,
                'public function workspace(',
                'public function publishedRegistry('
            );

        foreach (
            [
                '->create(',
                'prepareDraft(',
                'DB::transaction(',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $serviceWorkspace
            );
        }
    }

    public function test_all_mutations_are_explicit_and_delegate_to_dynamic_service(): void
    {
        foreach (
            [
                'public function prepareDraft(',
                '$service->prepareDraft(',

                'public function createEntity(',
                '$service->createEntity(',

                'public function replaceFields(',
                '$service->replaceFields(',

                'public function replaceRelationships(',
                '$service->replaceRelationships(',

                'public function publish(',
                '$service->publish(',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->controller
            );
        }
    }

    public function test_canonical_model_is_admin_lauda_only(): void
    {
        foreach (
            [
                "&& (string) \$actor->role === 'admin'",
                'DataTransformationBiTenantSourceWorkspaceGate::class',
                "'data_transformation_bi'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->controller
            );
        }

        self::assertStringContainsString(
            "middleware(['auth', 'verified', 'role:admin'])",
            $this->routes
        );
    }

    public function test_registry_scope_is_company_owned_not_request_owned(): void
    {
        $registryScope =
            $this->methodBlock(
                $this->controller,
                'private function scopedRegistry(',
                'private function scopedEntity('
            );

        foreach (
            [
                'DataTransformationBiCanonicalRegistryVersion::query()',
                "'company_id'",
                '$implementationRequest->company_id',
                'abort_unless(',
                '404',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $registryScope
            );
        }

        self::assertStringNotContainsString(
            "'source_transformation_implementation_request_id'",
            $registryScope
        );
    }

    public function test_entity_scope_is_registry_and_company_bound(): void
    {
        $entityScope =
            $this->methodBlock(
                $this->controller,
                'private function scopedEntity(',
                'private function workspaceResponse('
            );

        foreach (
            [
                'DataTransformationBiCanonicalEntity::query()',
                "'canonical_registry_version_id'",
                "'company_id'",
                '$registry->getKey()',
                '$implementationRequest->company_id',
                'abort_unless(',
                '404',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $entityScope
            );
        }
    }

    public function test_http_contract_has_dynamic_entity_field_and_relationship_validation(): void
    {
        foreach (
            [
                "'entity_key'",
                "'fields'",
                "'fields.*.field_key'",
                "'fields.*.data_type'",
                'in:text,integer,decimal,boolean,date,datetime',
                "'fields.*.is_identity'",
                "'relationships'",
                "'relationships.*.from_entity_key'",
                "'relationships.*.from_field_key'",
                "'relationships.*.to_entity_key'",
                "'relationships.*.to_field_key'",
                "'relationships.*.relationship_type'",
                'in:one_to_one,one_to_many,many_to_one,many_to_many',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->controller
            );
        }
    }

    public function test_http_v2_does_not_depend_on_legacy_seven_domain_registry(): void
    {
        foreach (
            [
                'DataTransformationBiStandardIntakeSchema',
                'DataTransformationBiSourceDomainRegistry',
                'DataTransformationBiCanonicalRegistry ',
                "'domain_key'",
                'domain_count',
                'STANDARD_INTAKE_BASE_VERSION',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->controller
            );
        }
    }

    private function methodBlock(
        string $source,
        string $startNeedle,
        string $endNeedle
    ): string {
        $start =
            strpos(
                $source,
                $startNeedle
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $source,
                $endNeedle,
                $start
            );

        self::assertNotFalse(
            $end
        );

        return substr(
            $source,
            $start,
            $end - $start
        );
    }
}
