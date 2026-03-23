<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\NavigationController;
use App\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(NavigationController::class)]
final class NavigationControllerTest extends TestCase
{
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        // Translator returns the key itself — sufficient for structure tests
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);
    }

    private function invokeController(string $role): JsonResponse
    {
        $user = $this->createMock(User::class);
        $user->method('getRoles')->willReturn([$role]);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $token = new UsernamePasswordToken($user, 'main', [$role]);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn(string $id) => $id === 'security.token_storage');
        $container->method('get')->willReturnCallback(fn(string $id) => match ($id) {
            'security.token_storage' => $tokenStorage,
            default => null,
        });

        $controller = new NavigationController($this->translator);
        $controller->setContainer($container);

        return $controller(new Request());
    }

    #[Test]
    public function adminGetsSectionsWithAllExpectedTitles(): void
    {
        $response = $this->invokeController('ROLE_ADMIN');

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        self::assertArrayHasKey('sections', $data);
        $titles = array_column($data['sections'], 'title');

        self::assertContains('sidebar.main', $titles);
        self::assertContains('sidebar.operations', $titles);
        self::assertContains('sidebar.administration', $titles);
        self::assertContains('sidebar.tracking', $titles);
        self::assertContains('sidebar.dev_tools', $titles);
    }

    #[Test]
    public function adminSectionsContainAllRouteInventoryHrefs(): void
    {
        $response = $this->invokeController('ROLE_ADMIN');
        $data = json_decode($response->getContent(), true);

        $allHrefs = [];
        foreach ($data['sections'] as $section) {
            foreach ($section['items'] as $item) {
                $allHrefs[] = $item['href'];
            }
        }

        // Routes from spec's Route Registry Inventory
        $expectedHrefs = [
            '/admin',
            '/app/admin/operator-dashboard',
            '/notifications',
            '/search',
            '/admin/vehicles',
            '/admin/drivers',
            '/admin/routes',
            '/admin/shipments',
            '/admin/shipments/import',
            '/app/admin/route-planner',
            '/admin/route-templates',
            '/admin/customers',
            '/admin/integrations',
            '/admin/users',
            '/admin/reports',
            '/admin/reports/sla',
            '/admin/reports/zone-trends',
            '/app/admin/exception-map',
            '/admin/billing',
            '/admin/optimization-logs',
            '/admin/ai-assistant',
            '/app/admin/fleet-map',
            '/admin/test-routing',
            '/admin/debug/routing',
            '/admin/fixtures',
            '/admin/commit-story',
        ];

        foreach ($expectedHrefs as $href) {
            self::assertContains($href, $allHrefs, "Missing admin menu href: $href");
        }
    }

    #[Test]
    public function itemsHaveRequiredStructure(): void
    {
        $response = $this->invokeController('ROLE_ADMIN');
        $data = json_decode($response->getContent(), true);

        foreach ($data['sections'] as $section) {
            self::assertArrayHasKey('title', $section);
            self::assertArrayHasKey('items', $section);
            foreach ($section['items'] as $item) {
                self::assertArrayHasKey('label', $item, "Item missing 'label'");
                self::assertArrayHasKey('href', $item, "Item missing 'href'");
                self::assertArrayHasKey('icon', $item, "Item missing 'icon'");
            }
        }
    }

    #[Test]
    public function responseHasCacheControlHeader(): void
    {
        $response = $this->invokeController('ROLE_ADMIN');
        self::assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function customerGetsCorrectSections(): void
    {
        $response = $this->invokeController('ROLE_CUSTOMER');
        $data = json_decode($response->getContent(), true);

        $titles = array_column($data['sections'], 'title');
        self::assertContains('sidebar.main', $titles);
        self::assertContains('sidebar.my_deliveries', $titles);
        self::assertContains('sidebar.tracking', $titles);

        // Should NOT have admin sections
        self::assertNotContains('sidebar.operations', $titles);
        self::assertNotContains('sidebar.dev_tools', $titles);

        // Dashboard should point to /customer/dashboard
        $allHrefs = [];
        foreach ($data['sections'] as $section) {
            foreach ($section['items'] as $item) {
                $allHrefs[] = $item['href'];
            }
        }
        self::assertContains('/customer/dashboard', $allHrefs);
        self::assertContains('/notifications', $allHrefs);
        self::assertContains('/search', $allHrefs);
    }

    #[Test]
    public function driverGetsMinimalSections(): void
    {
        $response = $this->invokeController('ROLE_DRIVER');
        $data = json_decode($response->getContent(), true);

        $titles = array_column($data['sections'], 'title');
        self::assertContains('sidebar.driver', $titles);
        self::assertCount(1, $data['sections']);

        $allHrefs = [];
        foreach ($data['sections'] as $section) {
            foreach ($section['items'] as $item) {
                $allHrefs[] = $item['href'];
            }
        }
        self::assertContains('/driver/routes', $allHrefs);
        self::assertContains('/notifications', $allHrefs);
    }
}
