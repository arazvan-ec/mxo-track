<?php

declare(strict_types=1);

namespace App\Tests\Functional\Smoke;

use App\Entity\Customer;
use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Smoke tests for non-admin pages: customer portal, driver pages.
 * Verifies these pages don't return 500 errors.
 *
 * Requires a test database. Run with: php vendor/bin/phpunit --group requires-db
 */
#[Group('requires-db')]
final class PageSmokeTest extends WebTestCase
{
    public function testCustomerPagesDoNotReturn500(): void
    {
        $client = static::createClient();

        $customer = new Customer('Smoke Test Customer');
        $customer->initializePublicId();

        $user = new User('customer-smoke@test.com');
        $user->assignRole(UserRole::CUSTOMER);
        $user->setCustomer($customer);

        $client->loginUser($user);

        $router = static::getContainer()->get('router');
        \assert($router instanceof RouterInterface);

        $routes = $this->getParameterlessGetRoutes($router, 'customer_');

        $failures = [];

        foreach ($routes as $name => $path) {
            $client->request('GET', $path);
            $statusCode = $client->getResponse()->getStatusCode();

            if ($statusCode === 500) {
                $failures[] = sprintf("  - %s (%s) → HTTP 500", $name, $path);
            }
        }

        self::assertSame(
            [],
            $failures,
            "Customer pages returning HTTP 500:\n" . implode("\n", $failures),
        );
    }

    public function testDriverPagesDoNotReturn500(): void
    {
        $client = static::createClient();

        $user = new User('driver-smoke@test.com');
        $user->assignRole(UserRole::DRIVER);

        $client->loginUser($user);

        $router = static::getContainer()->get('router');
        \assert($router instanceof RouterInterface);

        $routes = $this->getParameterlessGetRoutes($router, 'driver_');

        $failures = [];

        foreach ($routes as $name => $path) {
            $client->request('GET', $path);
            $statusCode = $client->getResponse()->getStatusCode();

            if ($statusCode === 500) {
                $failures[] = sprintf("  - %s (%s) → HTTP 500", $name, $path);
            }
        }

        self::assertSame(
            [],
            $failures,
            "Driver pages returning HTTP 500:\n" . implode("\n", $failures),
        );
    }

    /**
     * @return array<string, string> Route name => path
     */
    private function getParameterlessGetRoutes(RouterInterface $router, string $prefix): array
    {
        $result = [];

        foreach ($router->getRouteCollection() as $name => $route) {
            \assert($route instanceof Route);

            $methods = $route->getMethods();
            if ($methods !== [] && !\in_array('GET', $methods, true)) {
                continue;
            }

            if (!str_starts_with($name, $prefix)) {
                continue;
            }

            if (preg_match('/\{[^}]+\}/', $route->getPath())) {
                continue;
            }

            $result[$name] = $route->getPath();
        }

        return $result;
    }
}
