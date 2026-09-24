<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDynamicCanonicalRegistryUiContractTest
    extends TestCase
{
    private string $view;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->view =
            file_get_contents(
                $root
                .'/resources/js/pages/Admin/'
                .'Transformation360/'
                .'ImplementationRequests/'
                .'Show.vue'
            );

        self::assertIsString(
            $this->view
        );
    }

    public function test_canonical_model_workspace_is_admin_data_bi_ui(): void
    {
        foreach (
            [
                'CANONICAL_MODEL_V2_ADMIN_UI',
                'Modelo canónico LAUDA',
                'Preparar modelo',
                'Publicar versión',
                'Entidades canónicas',
                'Relaciones canónicas',
                'Borrador',
                'Publicado',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->view
            );
        }
    }

    public function test_initial_workspace_load_is_explicit_read_only_get(): void
    {
        $block =
            $this->methodBlock(
                'async function loadCanonicalModelWorkspace(',
                'async function prepareCanonicalModel('
            );

        self::assertStringContainsString(
            'canonicalModelBaseUrl',
            $block
        );

        self::assertStringContainsString(
            "method:\n                        'GET'",
            $block
        );

        foreach (
            [
                '/prepare',
                '/entities',
                '/fields',
                '/relationships',
                '/publish',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }

    public function test_prepare_entity_fields_relationships_and_publish_are_explicit_writes(): void
    {
        $prepare =
            $this->methodBlock(
                'async function prepareCanonicalModel(',
                'async function createCanonicalEntity('
            );

        self::assertStringContainsString(
            '/prepare',
            $prepare
        );

        self::assertStringContainsString(
            "method:\n                        'POST'",
            $prepare
        );

        $entity =
            $this->methodBlock(
                'async function createCanonicalEntity(',
                'function addCanonicalField('
            );

        self::assertStringContainsString(
            '/entities`',
            $entity
        );

        self::assertStringContainsString(
            "method:\n                        'POST'",
            $entity
        );

        $fields =
            $this->methodBlock(
                'async function saveCanonicalEntityFields(',
                'function addCanonicalRelationship('
            );

        self::assertStringContainsString(
            '/fields`',
            $fields
        );

        self::assertStringContainsString(
            "method:\n                        'PUT'",
            $fields
        );

        $relationships =
            $this->methodBlock(
                'async function saveCanonicalRelationships(',
                'async function publishCanonicalModel('
            );

        self::assertStringContainsString(
            '/relationships`',
            $relationships
        );

        self::assertStringContainsString(
            "method:\n                        'PUT'",
            $relationships
        );

        $publish =
            $this->methodBlock(
                'async function publishCanonicalModel(',
                'function standardIntakeV2SessionStatusLabel('
            );

        self::assertStringContainsString(
            '/publish`',
            $publish
        );

        self::assertStringContainsString(
            "method:\n                        'POST'",
            $publish
        );
    }

    public function test_dynamic_entity_field_and_relationship_definition_is_exposed(): void
    {
        foreach (
            [
                'canonicalEntityForm.entity_key',
                'canonicalEntityForm.label',
                'field.field_key',
                'field.data_type',
                'field.required',
                'field.is_identity',
                'relationship.from_entity_key',
                'relationship.from_field_key',
                'relationship.to_entity_key',
                'relationship.to_field_key',
                'relationship.relationship_type',
                'one_to_one',
                'one_to_many',
                'many_to_one',
                'many_to_many',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->view
            );
        }
    }

    public function test_mapping_remains_bound_to_published_model(): void
    {
        self::assertStringContainsString(
            'Mapeo LAUDA usa únicamente la versión',
            $this->view
        );

        self::assertStringContainsString(
            'publicada',
            $this->view
        );

        self::assertStringContainsString(
            'loadDynamicSourceMappingWorkspace(',
            $this->view
        );

        self::assertStringContainsString(
            '/mapping-workspace',
            $this->view
        );
    }

    public function test_canonical_ui_does_not_reintroduce_legacy_seven_domain_contract(): void
    {
        $block =
            $this->methodBlock(
                '<!-- CANONICAL_MODEL_V2_ADMIN_UI -->',
                '<!-- D17_DYNAMIC_SOURCE_WORKSPACE_UI -->'
            );

        foreach (
            [
                'DataTransformationBiStandardIntakeSchema',
                'domain_count',
                'STANDARD_INTAKE_BASE_VERSION',
                'customers, products',
                '7 dominios',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }

    public function test_first_version_is_presented_as_empty_dynamic_model(): void
    {
        $block =
            $this->methodBlock(
                '<!-- CANONICAL_MODEL_V2_ADMIN_UI -->',
                '<!-- D17_DYNAMIC_SOURCE_WORKSPACE_UI -->'
            );

        foreach (
            [
                'La primera versión comienza vacía',
                'no se crean dominios predefinidos',
                'Preparar primer modelo',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $block
            );
        }
    }

    public function test_ui_consumes_real_canonical_field_type_payload(): void
    {
        foreach (
            [
                'type?: string;',
                'data_type?: string;',
                'function canonicalModelFieldType(',
                'field.type',
                'canonicalModelFieldType(',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->view
            );
        }

        $hydrate =
            $this->methodBlock(
                'function hydrateCanonicalModelDrafts(): void {',
                'async function canonicalModelRequest('
            );

        self::assertStringContainsString(
            'canonicalModelFieldType(',
            $hydrate
        );

        self::assertStringNotContainsString(
            'field.data_type',
            $hydrate
        );

        $template =
            $this->methodBlock(
                '<!-- CANONICAL_MODEL_V2_ADMIN_UI -->',
                '<!-- D17_DYNAMIC_SOURCE_WORKSPACE_UI -->'
            );

        self::assertStringContainsString(
            'canonicalModelFieldType(',
            $template
        );
    }

    private function methodBlock(
        string $startNeedle,
        string $endNeedle
    ): string {
        $start =
            strpos(
                $this->view,
                $startNeedle
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->view,
                $endNeedle,
                $start
            );

        self::assertNotFalse(
            $end
        );

        return substr(
            $this->view,
            $start,
            $end - $start
        );
    }
}
