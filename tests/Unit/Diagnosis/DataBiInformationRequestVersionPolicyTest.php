<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiInformationRequestVersionPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DataBiInformationRequestVersionPolicyTest extends TestCase
{
    private const A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_first_save_creates_version_one(): void
    {
        self::assertSame(['action' => 'append', 'version' => 1], DataBiInformationRequestVersionPolicy::decide(null, self::A, 0));
    }

    public function test_missing_previous_row_rejects_nonzero_expected_version(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DataBiInformationRequestVersionPolicy::decide(null, self::A, 1);
    }

    public function test_changed_review_creates_next_version(): void
    {
        self::assertSame(['action' => 'append', 'version' => 3], DataBiInformationRequestVersionPolicy::decide($this->latest(), self::B, 2));
    }

    public function test_retry_of_same_review_is_idempotent(): void
    {
        self::assertSame(['action' => 'unchanged', 'version' => 2], DataBiInformationRequestVersionPolicy::decide($this->latest(), self::A, 1));
    }

    public function test_stale_different_review_cannot_overwrite(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DataBiInformationRequestVersionPolicy::decide($this->latest(), self::B, 1);
    }

    public function test_sent_version_is_immutable_even_with_same_hash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DataBiInformationRequestVersionPolicy::decide([...$this->latest(), 'status' => 'sent'], self::A, 2);
    }

    public function test_rejects_invalid_fingerprint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DataBiInformationRequestVersionPolicy::decide(null, 'broken', 0);
    }

    public function test_rejects_future_version_even_with_identical_content(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DataBiInformationRequestVersionPolicy::decide($this->latest(), self::A, 3);
    }

    public function test_rejects_version_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DataBiInformationRequestVersionPolicy::decide([...$this->latest(), 'version' => 10000], self::B, 10000);
    }

    private function latest(): array
    {
        return ['status' => 'draft', 'version' => 2, 'content_sha256' => self::A];
    }
}
