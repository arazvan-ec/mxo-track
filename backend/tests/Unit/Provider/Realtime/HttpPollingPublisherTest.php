<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Realtime;

use App\Entity\Customer;
use App\Entity\RealtimeEvent;
use App\Provider\Realtime\HttpPollingPublisher;
use App\Provider\TenantContext;
use App\Realtime\RealtimePublisherInterface;
use App\Realtime\SseMessage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpPollingPublisher::class)]
final class HttpPollingPublisherTest extends TestCase
{
    private EntityManagerInterface $em;
    private TenantContext $tenantContext;
    private HttpPollingPublisher $publisher;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->tenantContext = $this->createMock(TenantContext::class);
        $this->publisher = new HttpPollingPublisher($this->em, $this->tenantContext);
    }

    #[Test]
    public function implementsRealtimePublisherInterface(): void
    {
        self::assertInstanceOf(RealtimePublisherInterface::class, $this->publisher);
    }

    #[Test]
    public function publishPersistsRealtimeEventForEachTopic(): void
    {
        $customer = new Customer('Test');
        $this->tenantContext->method('getCustomer')->willReturn($customer);

        $message = new SseMessage(
            data: ['position' => [40.0, -3.7]],
            topics: ['/vehicles/abc/position', '/operator/fleet'],
            type: 'position_update',
        );

        $persisted = [];
        $this->em->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $this->em->expects(self::once())->method('flush');

        $this->publisher->publish($message);

        self::assertCount(2, $persisted);
        self::assertInstanceOf(RealtimeEvent::class, $persisted[0]);
        self::assertInstanceOf(RealtimeEvent::class, $persisted[1]);
        self::assertSame('/vehicles/abc/position', $persisted[0]->getTopic());
        self::assertSame('/operator/fleet', $persisted[1]->getTopic());
        self::assertSame(['position' => [40.0, -3.7]], $persisted[0]->getData());
        self::assertSame('position_update', $persisted[0]->getEventType());
    }

    #[Test]
    public function publishHandlesStringDataByWrappingInRawKey(): void
    {
        $customer = new Customer('Test');
        $this->tenantContext->method('getCustomer')->willReturn($customer);

        $message = new SseMessage(
            data: 'some string payload',
            topics: ['/topic/one'],
        );

        $persisted = [];
        $this->em->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $this->em->expects(self::once())->method('flush');

        $this->publisher->publish($message);

        self::assertCount(1, $persisted);
        self::assertSame(['raw' => 'some string payload'], $persisted[0]->getData());
    }

    #[Test]
    public function publishHandlesArrayDataDirectly(): void
    {
        $customer = new Customer('Test');
        $this->tenantContext->method('getCustomer')->willReturn($customer);

        $data = ['key' => 'value', 'nested' => ['a' => 1]];
        $message = new SseMessage(data: $data, topics: ['/topic']);

        $persisted = [];
        $this->em->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $this->em->method('flush');

        $this->publisher->publish($message);

        self::assertSame($data, $persisted[0]->getData());
    }

    #[Test]
    public function publishSkipsWhenNoTenantContext(): void
    {
        $this->tenantContext->method('getCustomer')->willReturn(null);

        $message = new SseMessage(data: ['x' => 1], topics: ['/topic']);

        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('flush');

        $this->publisher->publish($message);
    }

    #[Test]
    public function publishBatchPublishesAllMessages(): void
    {
        $customer = new Customer('Test');
        $this->tenantContext->method('getCustomer')->willReturn($customer);

        $messages = [
            new SseMessage(data: ['a' => 1], topics: ['/topic/a']),
            new SseMessage(data: ['b' => 2], topics: ['/topic/b']),
        ];

        $persisted = [];
        $this->em->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $this->em->expects(self::exactly(2))->method('flush');

        $this->publisher->publishBatch($messages);

        self::assertCount(2, $persisted);
        self::assertSame('/topic/a', $persisted[0]->getTopic());
        self::assertSame('/topic/b', $persisted[1]->getTopic());
    }
}
