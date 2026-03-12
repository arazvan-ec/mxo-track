<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Gate;

use App\Entity\Customer;
use App\Enum\NotificationChannel;
use App\Notification\Gate\CustomerNotificationQuota;
use App\Repository\NotificationLogRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomerNotificationQuota::class)]
final class CustomerNotificationQuotaTest extends TestCase
{
    private NotificationLogRepository&MockObject $logRepo;
    private CustomerNotificationQuota $quota;

    protected function setUp(): void
    {
        $this->logRepo = $this->createMock(NotificationLogRepository::class);
        $this->quota = new CustomerNotificationQuota($this->logRepo);
    }

    #[Test]
    public function it_allows_when_under_default_quota(): void
    {
        $customer = new Customer('Test Corp');
        $this->logRepo->method('countSentByCustomerSince')->willReturn(500);

        self::assertTrue($this->quota->canSend($customer, NotificationChannel::Sms));
    }

    #[Test]
    public function it_blocks_when_default_quota_exceeded(): void
    {
        $customer = new Customer('Test Corp');
        $this->logRepo->method('countSentByCustomerSince')->willReturn(1000);

        self::assertFalse($this->quota->canSend($customer, NotificationChannel::Sms));
    }

    #[Test]
    public function it_uses_customer_custom_quota(): void
    {
        $customer = new Customer('Test Corp');
        $customer->setNotificationQuota(50);
        $this->logRepo->method('countSentByCustomerSince')->willReturn(49);

        self::assertTrue($this->quota->canSend($customer, NotificationChannel::Sms));
    }

    #[Test]
    public function it_blocks_when_custom_quota_exceeded(): void
    {
        $customer = new Customer('Test Corp');
        $customer->setNotificationQuota(50);
        $this->logRepo->method('countSentByCustomerSince')->willReturn(50);

        self::assertFalse($this->quota->canSend($customer, NotificationChannel::Sms));
    }

    #[Test]
    public function it_reports_remaining_quota(): void
    {
        $customer = new Customer('Test Corp');
        $this->logRepo->method('countSentByCustomerSince')->willReturn(800);

        self::assertSame(200, $this->quota->remaining($customer, NotificationChannel::Sms));
    }
}
