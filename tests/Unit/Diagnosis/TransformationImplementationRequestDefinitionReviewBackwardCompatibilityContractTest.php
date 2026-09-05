<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionReviewBackwardCompatibilityContractTest
    extends TestCase
{
    private function reviewMethod(): string
    {
        $source =
            file_get_contents(
                dirname(__DIR__, 3)
                .'/app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        $start =
            strpos(
                $source,
                'public function review('
            );

        $end =
            strpos(
                $source,
                'public function submitForTenantReview(',
                $start
            );

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr(
            $source,
            $start,
            $end - $start
        );
    }

    public function test_functional_blocks_are_optional_http_inputs(): void
    {
        $review =
            $this->reviewMethod();

        $this->assertMatchesRegularExpression(
            "/'implementation_scope'\\s*=>\\s*\\[\\s*'sometimes'\\s*,\\s*'array'\\s*\\]/s",
            $review
        );

        $this->assertMatchesRegularExpression(
            "/'deliverables'\\s*=>\\s*\\[\\s*'sometimes'\\s*,\\s*'array'\\s*,\\s*'min:1'\\s*\\]/s",
            $review
        );

        $this->assertMatchesRegularExpression(
            "/'dependencies'\\s*=>\\s*\\[\\s*'sometimes'\\s*,\\s*'array'\\s*\\]/s",
            $review
        );
    }

    public function test_functional_edit_capability_remains_available(): void
    {
        $review =
            $this->reviewMethod();

        foreach ([
            "'implementation_scope'",
            "'deliverables'",
            "'dependencies'",
            "'responsibility_model'",
            "'readiness'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $review
            );
        }
    }

    public function test_legacy_partial_payload_remains_supported(): void
    {
        $review =
            $this->reviewMethod();

        foreach ([
            'implementation_scope',
            'deliverables',
            'dependencies',
        ] as $field) {
            $this->assertDoesNotMatchRegularExpression(
                "/'"
                .preg_quote($field, '/')
                ."'\\s*=>\\s*\\[[^\\]]*'required'/s",
                $review
            );
        }
    }
}
