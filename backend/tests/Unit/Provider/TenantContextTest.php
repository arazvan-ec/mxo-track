<?php
declare(strict_types=1);
namespace App\Tests\Unit\Provider;

use App\Entity\Customer;
use App\Entity\User;
use App\Provider\TenantContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[CoversClass(TenantContext::class)]
final class TenantContextTest extends TestCase
{
    #[Test]
    public function it_returns_customer_when_user_has_customer(): void
    {
        $customer = new Customer('Test Corp');
        $user = new User('user@test.com');
        $user->setCustomer($customer);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $context = new TenantContext($security);
        self::assertSame($customer, $context->getCustomer());
    }

    #[Test]
    public function it_returns_null_for_admin_without_customer(): void
    {
        $user = new User('admin@test.com');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $context = new TenantContext($security);
        self::assertNull($context->getCustomer());
    }

    #[Test]
    public function it_returns_null_when_no_user_authenticated(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $context = new TenantContext($security);
        self::assertNull($context->getCustomer());
    }
}
