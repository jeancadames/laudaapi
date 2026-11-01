<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiInformationRequestSendPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DataBiInformationRequestSendPolicyTest extends TestCase
{
    private function latest(
        string $status = 'draft',
        int $version = 3
    ): array {
        $hash = str_repeat('a', 64);

        return [
            'version' => $version,
            'status' => $status,
            'content_sha256' => $hash,
            'review_snapshot' => [
                'schema_version' => 1,
                'purpose' => 'information_source_declaration',
                'origin' => 'tenant_business_needs_snapshot',
                'content_sha256' => $hash,
                'data_inspected' => false,
                'tenant_response_received' => false,
                'requirements' => [[
                    'key' => 'clientes',
                    'label' => 'Clientes',
                    'guidance' => 'Identificar las fuentes disponibles',
                    'objective_keys' => ['gestion_clientes'],
                ]],
            ],
        ];
    }

    public function test_allows_latest_draft(): void
    {
        self::assertSame(
            ['action' => 'send', 'version' => 3],
            DataBiInformationRequestSendPolicy::decide(
                $this->latest(),
                3
            )
        );
    }

    public function test_repeated_send_is_idempotent(): void
    {
        self::assertSame(
            ['action' => 'already_sent', 'version' => 3],
            DataBiInformationRequestSendPolicy::decide(
                $this->latest('sent'),
                3
            )
        );
    }

    public function test_newer_revision_can_be_sent(): void
    {
        self::assertSame(
            ['action' => 'send', 'version' => 4],
            DataBiInformationRequestSendPolicy::decide(
                $this->latest('draft', 4),
                4
            )
        );
    }

    public function test_rejects_old_version(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DataBiInformationRequestSendPolicy::decide(
            $this->latest(),
            2
        );
    }

    public function test_rejects_invalid_status(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DataBiInformationRequestSendPolicy::decide(
            $this->latest('submitted'),
            3
        );
    }

    public function test_rejects_hash_mismatch(): void
    {
        $row = $this->latest();

        $row['review_snapshot']['content_sha256'] =
            str_repeat('b', 64);

        $this->expectException(InvalidArgumentException::class);

        DataBiInformationRequestSendPolicy::decide($row, 3);
    }

    public function test_rejects_inspected_data(): void
    {
        $row = $this->latest();

        $row['review_snapshot']['data_inspected'] = true;

        $this->expectException(InvalidArgumentException::class);

        DataBiInformationRequestSendPolicy::decide($row, 3);
    }

    public function test_rejects_empty_requirements(): void
    {
        $row = $this->latest();

        $row['review_snapshot']['requirements'] = [];

        $this->expectException(InvalidArgumentException::class);

        DataBiInformationRequestSendPolicy::decide($row, 3);
    }

    public function test_rejects_duplicate_keys(): void
    {
        $row = $this->latest();

        $row['review_snapshot']['requirements'][] =
            $row['review_snapshot']['requirements'][0];

        $this->expectException(InvalidArgumentException::class);

        DataBiInformationRequestSendPolicy::decide($row, 3);
    }

    public function test_rejects_missing_objective_links(): void
    {
        $row = $this->latest();

        $row['review_snapshot']['requirements'][0]['objective_keys'] =
            [];

        $this->expectException(InvalidArgumentException::class);

        DataBiInformationRequestSendPolicy::decide($row, 3);
    }
}
