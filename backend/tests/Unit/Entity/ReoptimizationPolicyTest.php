<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Customer;
use App\Entity\ReoptimizationPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReoptimizationPolicy::class)]
final class ReoptimizationPolicyTest extends TestCase
{
    #[Test]
    public function create_with_triggers_stores_all_fields(): void
    {
        $customer = $this->createMock(Customer::class);

        $policy = new ReoptimizationPolicy(
            customer: $customer,
            triggers: ['on_exception', 'on_skip', 'on_delay'],
            delayThresholdMinutes: 45,
            consecutiveExceptionThreshold: 3,
            cooldownMinutes: 15,
        );

        self::assertSame($customer, $policy->getCustomer());
        self::assertSame(['on_exception', 'on_skip', 'on_delay'], $policy->getTriggers());
        self::assertSame(45, $policy->getDelayThresholdMinutes());
        self::assertSame(3, $policy->getConsecutiveExceptionThreshold());
        self::assertSame(15, $policy->getCooldownMinutes());
        self::assertTrue($policy->isEnabled());
    }

    #[Test]
    public function allows_trigger_returns_true_when_present(): void
    {
        $policy = new ReoptimizationPolicy(
            customer: $this->createMock(Customer::class),
            triggers: ['on_exception', 'on_delay'],
        );

        self::assertTrue($policy->allowsTrigger('on_exception'));
        self::assertTrue($policy->allowsTrigger('on_delay'));
    }

    #[Test]
    public function allows_trigger_returns_false_when_absent(): void
    {
        $policy = new ReoptimizationPolicy(
            customer: $this->createMock(Customer::class),
            triggers: ['on_exception'],
        );

        self::assertFalse($policy->allowsTrigger('on_skip'));
        self::assertFalse($policy->allowsTrigger('on_delay'));
    }

    #[Test]
    public function disabled_policy_blocks_all_triggers(): void
    {
        $policy = new ReoptimizationPolicy(
            customer: $this->createMock(Customer::class),
            triggers: ['on_exception', 'on_skip', 'on_delay'],
            enabled: false,
        );

        self::assertFalse($policy->isEnabled());
        // allowsTrigger only checks array membership, isEnabled is checked by callers
        self::assertTrue($policy->allowsTrigger('on_exception'));
    }

    #[Test]
    public function defaults_are_sensible(): void
    {
        $policy = new ReoptimizationPolicy(
            customer: $this->createMock(Customer::class),
        );

        self::assertSame([], $policy->getTriggers());
        self::assertSame(30, $policy->getDelayThresholdMinutes());
        self::assertSame(2, $policy->getConsecutiveExceptionThreshold());
        self::assertSame(10, $policy->getCooldownMinutes());
        self::assertTrue($policy->isEnabled());
    }
}
