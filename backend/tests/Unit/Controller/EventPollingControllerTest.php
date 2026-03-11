<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\Api\V1\EventPollingController;
use App\Entity\Customer;
use App\Entity\RealtimeEvent;
use App\Provider\TenantContext;
use App\Repository\RealtimeEventRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(EventPollingController::class)]
final class EventPollingControllerTest extends TestCase
{
    private TenantContext $tenantContext;
    private RealtimeEventRepository $repository;
    private EventPollingController $controller;

    protected function setUp(): void
    {
        $this->tenantContext = $this->createMock(TenantContext::class);
        $this->repository = $this->createMock(RealtimeEventRepository::class);
        $this->controller = new EventPollingController();
    }

    #[Test]
    public function pollReturnsJsonArrayOfEvents(): void
    {
        $customer = new Customer('Test Customer');
        $this->tenantContext->method('getCustomer')->willReturn($customer);

        $event1 = new RealtimeEvent($customer, '/vehicles/abc/position', ['lat' => 40.0], 'position_update');
        $event2 = new RealtimeEvent($customer, '/vehicles/abc/position', ['lat' => 41.0], 'position_update');

        $this->repository->method('findSince')->willReturn([$event1, $event2]);

        $request = new Request(['since' => '2026-03-11T00:00:00+00:00']);

        $response = $this->controller->poll($request, $this->repository, $this->tenantContext);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertCount(2, $data);
        self::assertSame('/vehicles/abc/position', $data[0]['topic']);
        self::assertEquals(['lat' => 40.0], $data[0]['data']);
        self::assertSame('position_update', $data[0]['type']);
        self::assertArrayHasKey('created_at', $data[0]);
    }

    #[Test]
    public function pollReturns403WhenNoTenantContext(): void
    {
        $this->tenantContext->method('getCustomer')->willReturn(null);

        $request = new Request();

        $response = $this->controller->poll($request, $this->repository, $this->tenantContext);

        self::assertSame(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('No tenant context', $data['error']);
    }

    #[Test]
    public function pollPassesTopicFilterToRepository(): void
    {
        $customer = new Customer('Test Customer');
        $this->tenantContext->method('getCustomer')->willReturn($customer);

        $this->repository->expects(self::once())
            ->method('findSince')
            ->with(
                $customer,
                '/vehicles/abc/position',
                self::isInstanceOf(\DateTimeImmutable::class),
            )
            ->willReturn([]);

        $request = new Request(['topic' => '/vehicles/abc/position', 'since' => '2026-03-11T00:00:00+00:00']);

        $response = $this->controller->poll($request, $this->repository, $this->tenantContext);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('[]', $response->getContent());
    }

    #[Test]
    public function pollDefaultsSinceToFiveMinutesAgo(): void
    {
        $customer = new Customer('Test Customer');
        $this->tenantContext->method('getCustomer')->willReturn($customer);

        $before = new \DateTimeImmutable('-5 minutes');

        $this->repository->expects(self::once())
            ->method('findSince')
            ->with(
                $customer,
                null,
                self::callback(function (\DateTimeImmutable $since) use ($before): bool {
                    // The since should be approximately 5 minutes ago
                    return $since >= $before && $since <= new \DateTimeImmutable();
                }),
            )
            ->willReturn([]);

        $request = new Request();

        $this->controller->poll($request, $this->repository, $this->tenantContext);
    }

    #[Test]
    public function pollPassesNullTopicWhenNotProvided(): void
    {
        $customer = new Customer('Test Customer');
        $this->tenantContext->method('getCustomer')->willReturn($customer);

        $this->repository->expects(self::once())
            ->method('findSince')
            ->with(
                $customer,
                null,
                self::isInstanceOf(\DateTimeImmutable::class),
            )
            ->willReturn([]);

        $request = new Request(['since' => '2026-03-11T00:00:00+00:00']);

        $this->controller->poll($request, $this->repository, $this->tenantContext);
    }
}
