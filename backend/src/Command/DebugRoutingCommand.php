<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:debug:routing',
    description: 'Diagnose OSRM and VROOM connectivity issues (DNS, TCP, HTTP)',
)]
final class DebugRoutingCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $osrmUrl = '',
        private readonly string $vroomUrl = '',
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('OSRM / VROOM Connectivity Diagnostics');
        $allOk = true;

        // Show configured URLs
        $io->section('1. Configuration');
        $io->table(['Variable', 'Value'], [
            ['OSRM_URL', $this->osrmUrl ?: '(empty)'],
            ['VROOM_URL', $this->vroomUrl ?: '(empty)'],
        ]);

        // OSRM diagnostics
        $io->section('2. OSRM Diagnostics');
        if ($this->osrmUrl === '') {
            $io->error('OSRM_URL is not configured. Set the OSRM_URL environment variable.');
            $allOk = false;
        } else {
            $allOk = $this->diagnoseService($io, 'OSRM', $this->osrmUrl) && $allOk;

            // OSRM-specific: test routing endpoint
            $io->text('  Testing OSRM /route/v1/driving endpoint...');
            $routeUrl = rtrim($this->osrmUrl, '/') . '/route/v1/driving/-3.7038,40.4168;-3.6883,40.4530?overview=false';
            $allOk = $this->testHttpGet($io, 'OSRM route', $routeUrl, function (array $data) use ($io): bool {
                if (($data['code'] ?? '') !== 'Ok') {
                    $io->error(sprintf('  OSRM returned code: %s (expected "Ok")', $data['code'] ?? 'null'));
                    if (isset($data['message'])) {
                        $io->error(sprintf('  Message: %s', $data['message']));
                    }
                    return false;
                }
                $route = $data['routes'][0] ?? null;
                if ($route) {
                    $io->success(sprintf(
                        '  Route OK: %.2f km, %d seconds',
                        ($route['distance'] ?? 0) / 1000,
                        $route['duration'] ?? 0,
                    ));
                }
                return true;
            }) && $allOk;
        }

        // VROOM diagnostics
        $io->section('3. VROOM Diagnostics');
        if ($this->vroomUrl === '') {
            $io->error('VROOM_URL is not configured. Set the VROOM_URL environment variable.');
            $allOk = false;
        } else {
            $allOk = $this->diagnoseService($io, 'VROOM', $this->vroomUrl) && $allOk;

            // VROOM-specific: test optimization endpoint
            $io->text('  Testing VROOM optimization endpoint...');
            try {
                $start = microtime(true);
                $response = $this->httpClient->request('POST', $this->vroomUrl, [
                    'json' => [
                        'vehicles' => [['id' => 0, 'start' => [-3.7038, 40.4168]]],
                        'jobs' => [['id' => 0, 'location' => [-3.6883, 40.4530]]],
                    ],
                    'timeout' => 10,
                ]);
                $statusCode = $response->getStatusCode();
                $latency = (int) round((microtime(true) - $start) * 1000);

                if ($statusCode >= 400) {
                    $body = $response->getContent(false);
                    $io->error(sprintf('  VROOM returned HTTP %d (%dms)', $statusCode, $latency));
                    $io->text(sprintf('  Response body: %s', mb_substr($body, 0, 500)));
                    $allOk = false;
                } else {
                    $data = $response->toArray();
                    $code = $data['code'] ?? -1;
                    if ($code === 0) {
                        $io->success(sprintf('  VROOM optimization OK (%dms)', $latency));
                    } else {
                        $io->error(sprintf('  VROOM returned error code %d (%dms)', $code, $latency));
                        if (isset($data['error'])) {
                            $io->error(sprintf('  Error: %s', $data['error']));
                        }
                        $io->warning('  This likely means VROOM cannot reach OSRM internally.');
                        $io->text('  Check that config-railway.yml points to the correct OSRM host.');
                        $allOk = false;
                    }
                }
            } catch (\Throwable $e) {
                $io->error(sprintf('  VROOM POST failed: %s', $e->getMessage()));
                $allOk = false;
            }
        }

        // Cross-service: VROOM -> OSRM connectivity
        $io->section('4. VROOM -> OSRM Internal Connectivity');
        $io->text('VROOM config-railway.yml points OSRM to: osrm-mxo.railway.internal:5000');
        $io->text('If VROOM HTTP responds but optimization fails with routing errors,');
        $io->text('it means VROOM cannot reach OSRM on the internal network.');
        $io->text('');
        $io->text('Possible fixes:');
        $io->listing([
            'Verify both services are in the same Railway project and environment',
            'Check OSRM service name matches "osrm-mxo" in Railway dashboard',
            'Verify OSRM has finished map processing (check OSRM deploy logs for "Starting osrm-routed")',
            'Check Railway service networking is enabled (Private Networking toggle)',
        ]);

        // DNS resolution test from PHP side
        $io->section('5. DNS Resolution (from this container)');
        foreach (['osrm-mxo.railway.internal', 'vroom-mxo.railway.internal'] as $host) {
            $ip = gethostbyname($host);
            if ($ip === $host) {
                $io->error(sprintf('  %s: DNS FAILED (cannot resolve)', $host));
                $allOk = false;
            } else {
                $io->success(sprintf('  %s -> %s', $host, $ip));
            }
        }

        // Environment info
        $io->section('6. Environment Info');
        $io->table(['Key', 'Value'], [
            ['PHP version', PHP_VERSION],
            ['Hostname', gethostname() ?: 'unknown'],
            ['RAILWAY_ENVIRONMENT', $_ENV['RAILWAY_ENVIRONMENT'] ?? $_SERVER['RAILWAY_ENVIRONMENT'] ?? '(not set)'],
            ['RAILWAY_SERVICE_NAME', $_ENV['RAILWAY_SERVICE_NAME'] ?? $_SERVER['RAILWAY_SERVICE_NAME'] ?? '(not set)'],
        ]);

        $io->section('Result');
        if ($allOk) {
            $io->success('All connectivity checks passed!');
            return Command::SUCCESS;
        }

        $io->error('Some checks failed. Review the output above for details.');
        return Command::FAILURE;
    }

    private function diagnoseService(SymfonyStyle $io, string $name, string $url): bool
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);
        $ok = true;

        // DNS resolution
        $io->text(sprintf('  DNS: resolving %s...', $host));
        $ip = gethostbyname($host);
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            $io->error(sprintf('  DNS FAILED for %s — host cannot be resolved', $host));
            $io->warning('  Is the service name correct? Check Railway dashboard.');
            return false;
        }
        $io->text(sprintf('  DNS OK: %s -> %s', $host, $ip));

        // TCP connection
        $io->text(sprintf('  TCP: connecting to %s:%d...', $host, $port));
        $socket = @fsockopen($host, (int) $port, $errno, $errstr, 5);
        if ($socket === false) {
            $io->error(sprintf('  TCP FAILED: %s (errno %d)', $errstr, $errno));
            $io->warning('  The service may not be running or the port may be wrong.');
            return false;
        }
        fclose($socket);
        $io->text('  TCP OK: connection established');

        // HTTP health (simple GET)
        $io->text(sprintf('  HTTP: GET %s...', $url));
        try {
            $start = microtime(true);
            $response = $this->httpClient->request('GET', $url, ['timeout' => 5]);
            $statusCode = $response->getStatusCode();
            $latency = (int) round((microtime(true) - $start) * 1000);
            $io->text(sprintf('  HTTP %d (%dms)', $statusCode, $latency));

            if ($statusCode >= 500) {
                $io->error(sprintf('  %s returned server error (HTTP %d)', $name, $statusCode));
                $ok = false;
            }
        } catch (\Throwable $e) {
            $io->error(sprintf('  HTTP FAILED: %s', $e->getMessage()));
            $ok = false;
        }

        return $ok;
    }

    private function testHttpGet(SymfonyStyle $io, string $label, string $url, callable $validator): bool
    {
        try {
            $start = microtime(true);
            $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);
            $statusCode = $response->getStatusCode();
            $latency = (int) round((microtime(true) - $start) * 1000);

            if ($statusCode >= 400) {
                $body = $response->getContent(false);
                $io->error(sprintf('  %s: HTTP %d (%dms)', $label, $statusCode, $latency));
                $io->text(sprintf('  Body: %s', mb_substr($body, 0, 300)));
                return false;
            }

            $data = $response->toArray();
            return $validator($data);
        } catch (\Throwable $e) {
            $io->error(sprintf('  %s failed: %s', $label, $e->getMessage()));
            return false;
        }
    }
}
