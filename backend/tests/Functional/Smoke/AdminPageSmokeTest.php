<?php

declare(strict_types=1);

namespace App\Tests\Functional\Smoke;

use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Smoke tests that verify admin pages return HTTP 200 (not 500).
 * Authenticates as an admin user and hits every GET admin route
 * that doesn't require path parameters.
 *
 * Requires a test database. Run with: php vendor/bin/phpunit --group requires-db
 */
#[Group('requires-db')]
final class AdminPageSmokeTest extends WebTestCase
{
    public function testAllAdminIndexPagesDoNotReturn500(): void
    {
        $client = static::createClient();

        $user = new User('admin-smoke@test.com');
        $user->assignRole(UserRole::ADMIN);
        $client->loginUser($user);

        $router = static::getContainer()->get('router');
        \assert($router instanceof RouterInterface);

        $routes = $this->getParameterlessGetRoutes($router, 'admin_');
        self::assertNotEmpty($routes, 'No admin routes found — check route configuration.');

        $failures = [];

        foreach ($routes as $name => $path) {
            $client->request('GET', $path);
            $statusCode = $client->getResponse()->getStatusCode();

            if ($statusCode === 500) {
                $failures[] = sprintf(
                    "  - %s (%s) → HTTP 500",
                    $name,
                    $path,
                );
            }
        }

        self::assertSame(
            [],
            $failures,
            "Admin pages returning HTTP 500:\n" . implode("\n", $failures),
        );
    }

    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
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
