<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class VroomApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $vroomUrl,
    ) {}

    /**
     * Sends a VRP optimization request to the VROOM API.
     *
     * @param list<array> $vehicles VROOM vehicle objects
     * @param list<array> $jobs    VROOM job objects
     * @return array{code: int, routes: list<array>, unassigned: list<array>, summary: array}
     *
     * @throws \RuntimeException if VROOM returns an error
     */
    public function optimize(array $vehicles, array $jobs): array
    {
        $payload = [
            'vehicles' => $vehicles,
            'jobs' => $jobs,
        ];

        $response = $this->httpClient->request('POST', $this->vroomUrl, [
            'json' => $payload,
            'timeout' => 30,
        ]);

        $data = $response->toArray();

        if (($data['code'] ?? -1) !== 0) {
            throw new \RuntimeException(sprintf(
                'VROOM optimization failed (code %d): %s',
                $data['code'] ?? -1,
                $data['error'] ?? 'Unknown error',
            ));
        }

        return $data;
    }
}
