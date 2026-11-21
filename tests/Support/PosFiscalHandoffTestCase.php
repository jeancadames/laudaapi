<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;
use PDO;
use Symfony\Component\Process\Process;

abstract class PosFiscalHandoffTestCase extends BusinessCommercialTransportTestCase
{
    protected function pos(array $input = [], int $status = 200): array
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/pos-cash.php'), $this->runtime]);
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR));
        $process->setTimeout(120)->mustRun();
        try {
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException($process->getOutput().$process->getErrorOutput(), previous: $error);
        }
        $this->assertSame($status, $result['status'], json_encode($result).' '.($result['status'] !== $status ? $this->logs() : ''));

        return $result;
    }

    protected function logs(): string
    {
        return @file_get_contents($this->runtime.'/commercial-server.log').' '.@file_get_contents($this->runtime.'/ecf/storage/logs/application.log');
    }

    protected function receiver(string $name): PDO
    {
        return new PDO('sqlite:'.$this->runtime.'/'.$name.'/database.sqlite');
    }

    protected function fiscal(string $path, array $payload = [], ?string $reference = null, string $method = 'post', int $status = 202): array
    {
        $issuer = config('business_identity.issuer');
        Http::allowStrayRequests([$issuer.'/*']);
        $client = Http::acceptJson()->withOptions(['verify' => $this->runtime.'/tls.pem', 'proxy' => ''])->timeout(30)
            ->withHeaders(['X-API-Key' => 'synthetic-business-key', 'X-API-Secret' => 'synthetic-business-secret']);
        if ($reference !== null) {
            $client = $client->withHeaders(['Idempotency-Key' => $reference]);
        }
        $response = $client->$method($issuer.'/v1/'.$path, $payload);
        $this->assertSame($status, $response->status(), $response->body().' '.$this->logs());

        return $response->json();
    }
}
