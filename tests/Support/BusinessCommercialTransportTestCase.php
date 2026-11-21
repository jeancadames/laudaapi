<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

abstract class BusinessCommercialTransportTestCase extends BusinessCommercialTestCase
{
    private ?Process $server = null;

    protected function tearDown(): void
    {
        $this->server?->stop(5);
        parent::tearDown();
    }

    protected function startServer(bool $fiscal = false): void
    {
        $ports = [];
        $sockets = [];
        for ($i = 0; $i < ($fiscal ? 3 : 2); $i++) {
            $sockets[$i] = stream_socket_server('tcp://127.0.0.1:0');
            $ports[] = (int) substr(strrchr(stream_socket_get_name($sockets[$i], false), ':'), 1);
        }
        foreach ($sockets as $socket) {
            fclose($socket);
        }
        config()->set('business_identity.issuer', 'https://127.0.0.1:'.$ports[0]);
        foreach (['ecf', 'pos', 'bys', 'cumplimiento'] as $service) {
            config()->set('business_identity.peers.'.$service.'.secret', str_repeat($service.'-synthetic-', 8));
        }
        $links = DB::table('business_identity_links')->get()->keyBy('service')->toArray();
        file_put_contents($this->runtime.'/commercial-fixture.json', json_encode(['identity' => config('business_identity'), 'links' => $links, 'fiscal' => $fiscal, 'capacities' => config('business_commercial_bridge.capacities_enabled', false)], JSON_THROW_ON_ERROR));
        chmod($this->runtime.'/commercial-fixture.json', 0600);
        if ($fiscal) {
            (new Process(['php', base_path('tests/Fixtures/BusinessCommercial/ecf-setup.php'), $this->runtime]))->setTimeout(90)->mustRun();
        }
        $this->server = new Process(['python3', base_path('tests/Fixtures/BusinessCommercial/serve.py'), $this->runtime, ...array_map('strval', $ports)]);
        $this->server->start();
        $deadline = microtime(true) + 10;
        while (! is_file($this->runtime.'/commercial-server-ready')) {
            if (! $this->server->isRunning() || microtime(true) > $deadline) {
                $this->fail('Synthetic TLS server failed: '.$this->server->getErrorOutput());
            }
            usleep(50000);
        }
    }

    protected function client(string $service, string $action, array $data = [], int $status = 200): array
    {
        $process = new Process(['php', base_path('tests/Fixtures/BusinessCommercial/client.php'), $this->runtime, $service]);
        $process->setInput(json_encode(['action' => $action, ...$data], JSON_THROW_ON_ERROR));
        $process->setTimeout(30);
        $process->mustRun();
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($status, $result['status'], $service.' '.$action.' '.($result['message'] ?? '').' '.($result['status'] !== $status ? @file_get_contents($this->runtime.'/commercial-server.log') : ''));

        return $result['result'] ?? [];
    }
}
