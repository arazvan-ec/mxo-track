<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Entity\Customer;
use App\Entity\ReoptimizationPolicy;
use App\Repository\ReoptimizationPolicyRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for ReoptimizationPolicyApiController.
 * Tests the controller methods directly (unit test, no HTTP kernel).
 */
final class ReoptimizationPolicyApiControllerTest extends TestCase
{
    #[Test]
    public function list_returns_policies_for_customer(): void
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('1');

        $policy = new ReoptimizationPolicy(
            customer: $customer,
            triggers: ['on_exception', 'on_skip'],
            delayThresholdMinutes: 45,
            cooldownMinutes: 15,
        );

        $repo = $this->createMock(ReoptimizationPolicyRepository::class);
        $repo->method('findAll')->willReturn([$policy]);

        $em = $this->createMock(EntityManagerInterface::class);

        $controller = new \App\Controller\Api\ReoptimizationPolicyApiController($repo, $em);

        $response = $controller->list();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertCount(1, $data);
        self::assertSame(['on_exception', 'on_skip'], $data[0]['triggers']);
        self::assertSame(45, $data[0]['delay_threshold_minutes']);
    }

    #[Test]
    public function create_returns_201_with_public_id(): void
    {
        $repo = $this->createMock(ReoptimizationPolicyRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        // Expect persist + flush
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        // Mock customer resolution
        $customer = $this->createMock(Customer::class);
        $customerRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $customerRepo->method('findOneBy')->willReturn($customer);
        $em->method('getRepository')->willReturn($customerRepo);

        $controller = new \App\Controller\Api\ReoptimizationPolicyApiController($repo, $em);

        $request = new Request(content: json_encode([
            'customer_public_id' => 'cust-123',
            'triggers' => ['on_exception', 'on_delay'],
            'delay_threshold_minutes' => 30,
            'cooldown_minutes' => 10,
            'enabled' => true,
        ]));

        $response = $controller->create($request);

        self::assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('public_id', $data);
        self::assertSame(['on_exception', 'on_delay'], $data['triggers']);
    }

    #[Test]
    public function update_returns_200_with_new_triggers(): void
    {
        $customer = $this->createMock(Customer::class);
        $policy = new ReoptimizationPolicy(
            customer: $customer,
            triggers: ['on_exception'],
        );

        $repo = $this->createMock(ReoptimizationPolicyRepository::class);
        $repo->method('findOneBy')->willReturn($policy);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $controller = new \App\Controller\Api\ReoptimizationPolicyApiController($repo, $em);

        $request = new Request(content: json_encode([
            'triggers' => ['on_exception', 'on_skip', 'on_delay'],
            'delay_threshold_minutes' => 60,
        ]));

        $response = $controller->update('test-policy-id', $request);

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame(['on_exception', 'on_skip', 'on_delay'], $data['triggers']);
        self::assertSame(60, $data['delay_threshold_minutes']);
    }
}
