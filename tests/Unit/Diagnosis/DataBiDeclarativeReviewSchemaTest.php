<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Revisión estática: solo lee PHP; no carga ni invoca up/down y no construye conexión. */
final class DataBiDeclarativeReviewSchemaTest extends TestCase
{
    #[DataProvider('migrations')]
    public function test_prepared_schema_has_restrictive_compatible_keys_and_short_explicit_names(string $file, string $table, string $prefix, array $names): void
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/database/migrations/'.$file);
        self::assertStringContainsString("Schema::create('".$table."'", $source);
        self::assertStringContainsString("\$table->engine = 'InnoDB'", $source);
        self::assertStringContainsString('->restrictOnDelete()->restrictOnUpdate()', $source);
        self::assertStringNotContainsString('cascade', strtolower($source));
        self::assertStringNotContainsString('Schema::table(', $source);
        self::assertStringNotContainsString('DB::', $source);
        self::assertStringNotContainsString('->change()', $source);
        self::assertStringContainsString('$table->foreign(', $source);
        self::assertStringContainsString("->references('id')->on(", $source);
        foreach ($names as $name) {
            self::assertLessThanOrEqual(64, strlen($prefix.$name.'_fk'));
        }
        if ($table !== 'data_bi_declaration_clarification_events') {
            foreach (['transformation_implementation_request_id', 'company_id', 'data_bi_information_request_id', 'data_bi_tenant_declaration_id'] as $column) {
                self::assertStringContainsString("\$table->unsignedBigInteger('".$column."')", $source);
            }
        }
    }

    public static function migrations(): iterable
    {
        yield ['2026_10_31_120000_create_data_bi_declaration_clarifications_table.php', 'data_bi_declaration_clarifications', 'dbdc_', ['request', 'company', 'questionnaire', 'declaration', 'creator', 'editor']];
        yield ['2026_10_31_120100_create_data_bi_declaration_clarification_events_table.php', 'data_bi_declaration_clarification_events', 'dbdce_', ['clarification', 'actor']];
        yield ['2026_10_31_120200_create_data_bi_declarative_review_closures_table.php', 'data_bi_declarative_review_closures', 'dbdrc_', ['request', 'company', 'questionnaire', 'declaration', 'actor']];
    }

    public function test_unique_defences_are_per_declaration_and_event_sequence_with_binary_hashes(): void
    {
        $root = dirname(__DIR__, 3).'/database/migrations/';
        $clarifications = file_get_contents($root.'2026_10_31_120000_create_data_bi_declaration_clarifications_table.php');
        $events = file_get_contents($root.'2026_10_31_120100_create_data_bi_declaration_clarification_events_table.php');
        $closures = file_get_contents($root.'2026_10_31_120200_create_data_bi_declarative_review_closures_table.php');
        self::assertStringContainsString("->unique(['data_bi_tenant_declaration_id', 'question_sha256'], 'dbdc_question_uq')", $clarifications);
        self::assertStringContainsString("->unique(['data_bi_declaration_clarification_id', 'sequence'], 'dbdce_sequence_uq')", $events);
        self::assertStringContainsString("->unique('data_bi_tenant_declaration_id', 'dbdrc_declaration_uq')", $closures);
        foreach ([$clarifications, $events, $closures] as $source) {
            self::assertStringContainsString("->charset('ascii')->collation('ascii_bin')", $source);
            self::assertStringContainsString('->dateTime(', $source);
        }
        $parents = ['2026_10_30_000000_create_data_bi_tenant_declarations_table.php',
            '2026_10_26_230000_create_data_bi_information_requests_table.php', '2026_09_05_121500_create_transformation_implementation_requests_tables.php'];
        foreach ($parents as $file) {
            self::assertStringContainsString('$table->id()', file_get_contents($root.$file));
        }
    }
}
