<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/admin/debug')]
#[IsGranted('ROLE_ADMIN')]
class DebugRoutingController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $osrmUrl,
        private readonly string $vroomUrl,
    ) {}

    #[Route('/routing', name: 'admin_debug_routing', methods: ['GET'])]
    public function diagnostics(): JsonResponse
    {
        $results = [
            'config' => [
                'OSRM_URL' => $this->osrmUrl ?: '(empty)',
                'VROOM_URL' => $this->vroomUrl ?: '(empty)',
            ],
            'dns' => [],
            'osrm' => ['reachable' => false, 'route_works' => false],
            'vroom' => ['reachable' => false, 'optimization_works' => false],
        ];

        // DNS resolution
        foreach (['osrm' => $this->osrmUrl, 'vroom' => $this->vroomUrl] as $name => $url) {
            $host = parse_url($url, PHP_URL_HOST);
            if ($host === null || $host === false) {
                $results['dns'][$name] = ['host' => null, 'resolved' => false, 'ip' => null];
                continue;
            }
            $ip = gethostbyname($host);
            $resolved = $ip !== $host || filter_var($host, FILTER_VALIDATE_IP);
            $results['dns'][$name] = ['host' => $host, 'resolved' => $resolved, 'ip' => $resolved ? $ip : null];
        }

        // OSRM: test route endpoint
        if ($this->osrmUrl !== '') {
            $routeUrl = rtrim($this->osrmUrl, '/') . '/route/v1/driving/-3.7038,40.4168;-3.6883,40.4530?overview=false';
            try {
                $start = microtime(true);
                $response = $this->httpClient->request('GET', $routeUrl, ['timeout' => 10]);
                $status = $response->getStatusCode();
                $latency = (int) round((microtime(true) - $start) * 1000);
                $data = $response->toArray(false);

                $results['osrm'] = [
                    'reachable' => true,
                    'http_status' => $status,
                    'latency_ms' => $latency,
                    'route_works' => ($data['code'] ?? '') === 'Ok',
                    'osrm_code' => $data['code'] ?? null,
                    'osrm_message' => $data['message'] ?? null,
                    'distance_km' => isset($data['routes'][0]['distance']) ? round($data['routes'][0]['distance'] / 1000, 2) : null,
                    'duration_s' => $data['routes'][0]['duration'] ?? null,
                ];
            } catch (\Throwable $e) {
                $results['osrm']['error'] = $e->getMessage();
            }
        }

        // VROOM: test optimization
        if ($this->vroomUrl !== '') {
            try {
                $start = microtime(true);
                $response = $this->httpClient->request('POST', $this->vroomUrl, [
                    'json' => [
                        'vehicles' => [['id' => 0, 'start' => [-3.7038, 40.4168]]],
                        'jobs' => [['id' => 0, 'location' => [-3.6883, 40.4530]]],
                    ],
                    'timeout' => 10,
                ]);
                $status = $response->getStatusCode();
                $latency = (int) round((microtime(true) - $start) * 1000);
                $data = $response->toArray(false);

                $vroomCode = $data['code'] ?? -1;
                $results['vroom'] = [
                    'reachable' => true,
                    'http_status' => $status,
                    'latency_ms' => $latency,
                    'optimization_works' => $vroomCode === 0,
                    'vroom_code' => $vroomCode,
                    'vroom_error' => $data['error'] ?? null,
                ];

                if ($vroomCode !== 0) {
                    $results['vroom']['hint'] = 'VROOM responded but optimization failed. This usually means VROOM cannot reach OSRM internally. Check config-railway.yml host.';
                }
            } catch (\Throwable $e) {
                $results['vroom']['error'] = $e->getMessage();
            }
        }

        // Summary
        $allOk = ($results['osrm']['route_works'] ?? false) && ($results['vroom']['optimization_works'] ?? false);
        $results['summary'] = [
            'all_ok' => $allOk,
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'hostname' => gethostname() ?: 'unknown',
        ];

        return $this->json($results);
    }
}
