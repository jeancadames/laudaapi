<?php

namespace Tests\Unit\BusinessIdentity;

use App\Services\BusinessIdentity\IdentityToken;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentityTokenTest extends TestCase
{
    private function codec(): IdentityToken
    {
        return new IdentityToken(str_repeat('test-key-', 8));
    }

    public function test_round_trip_keeps_explicit_identity_and_different_nonces(): void
    {
        $one = $this->codec()->encode('https://hub.example', 'bys', 'launch', ['company' => 41], 1000, 60);
        $two = $this->codec()->encode('https://hub.example', 'bys', 'launch', ['company' => 41], 1000, 60);
        $claims = $this->codec()->decode($one, 'https://hub.example', 'bys', 'launch', 1001);
        $this->assertSame(['company' => 41], $claims['data']);
        $this->assertSame(1060, $claims['exp']);
        $this->assertNotSame($claims['nonce'], $this->codec()->decode($two, 'https://hub.example', 'bys', 'launch', 1001)['nonce']);
    }

    public static function invalidContexts(): array
    {
        return [
            'issuer' => ['https://other.example', 'bys', 'launch', 1001],
            'audience' => ['https://hub.example', 'pos', 'launch', 1001],
            'purpose' => ['https://hub.example', 'bys', 'link-proof', 1001],
            'expiry boundary' => ['https://hub.example', 'bys', 'launch', 1060],
            'future issue' => ['https://hub.example', 'bys', 'launch', 969],
            'missing issuer' => ['', 'bys', 'launch', 1001],
        ];
    }

    #[DataProvider('invalidContexts')]
    public function test_rejects_wrong_receiver_purpose_and_time(string $issuer, string $audience, string $purpose, int $now): void
    {
        $token = $this->codec()->encode('https://hub.example', 'bys', 'launch', [], 1000, 60);
        $this->expectException(InvalidArgumentException::class);
        $this->codec()->decode($token, $issuer, $audience, $purpose, $now);
    }

    public function test_each_application_requires_its_own_key(): void
    {
        $token = $this->codec()->encode('hub', 'ecf', 'launch', [], 1000);
        $this->expectException(InvalidArgumentException::class);
        (new IdentityToken(str_repeat('other', 12)))->decode($token, 'hub', 'ecf', 'launch', 1001);
    }

    public function test_tampering_is_rejected_without_echoing_token(): void
    {
        $token = $this->codec()->encode('hub', 'ecf', 'launch', [], 1000);
        $this->expectExceptionMessage('El comprobante de identidad no es válido o ha vencido.');
        $token[30] = $token[30] === 'x' ? 'y' : 'x';
        $this->codec()->decode($token, 'hub', 'ecf', 'launch', 1001);
    }

    public static function invalidBindings(): array
    {
        return [
            'missing company' => ['central_company_id', null], 'string company' => ['central_company_id', '1'],
            'negative subscriber' => ['central_subscriber_id', -1], 'zero user' => ['central_user_id', 0],
            'array user' => ['local_user_id', []], 'boolean company' => ['local_company_id', true],
            'other service' => ['service', 'social'], 'broken uuid' => ['link_id', str_repeat('-', 36)],
            'short consent' => ['consent', 'a'],
        ];
    }

    #[DataProvider('invalidBindings')]
    public function test_binding_does_not_coerce_untrusted_identifiers(string $key, mixed $value): void
    {
        $data = [
            'generation' => 1, 'central_company_id' => 1, 'central_subscriber_id' => 2, 'central_user_id' => 3,
            'local_company_id' => 4, 'local_user_id' => 5, 'service' => 'bys',
            'link_id' => '11111111-1111-4111-8111-111111111111', 'consent' => str_repeat('a', 32),
        ];
        IdentityToken::assertBinding($data);
        $data[$key] = $value;
        $this->expectException(InvalidArgumentException::class);
        IdentityToken::assertBinding($data);
    }

    public function test_short_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IdentityToken('short');
    }

    public function test_excessive_lifetime_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->codec()->encode('hub', 'pos', 'launch', [], 1000, 601);
    }
}
