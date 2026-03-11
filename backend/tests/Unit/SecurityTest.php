<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\User;
use App\EventSubscriber\SecurityHeadersSubscriber;
use App\Security\UserChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

#[CoversClass(SecurityHeadersSubscriber::class)]
#[CoversClass(UserChecker::class)]
final class SecurityTest extends TestCase
{
    #[Test]
    public function securityHeadersSubscriberAddsAllRequiredHeaders(): void
    {
        $subscriber = new SecurityHeadersSubscriber('http://localhost:3000/.well-known/mercure');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');
        $response = new Response();
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        self::assertNotNull($response->headers->get('Content-Security-Policy'));
    }

    #[Test]
    public function securityHeadersCspContainsMercureOrigin(): void
    {
        $subscriber = new SecurityHeadersSubscriber('http://localhost:3000/.well-known/mercure');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');
        $response = new Response();
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $csp = $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString('http://localhost:3000', $csp);
    }

    #[Test]
    public function securityHeadersSkipsSubRequests(): void
    {
        $subscriber = new SecurityHeadersSubscriber('http://localhost:3000');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');
        $response = new Response();
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        self::assertNull($response->headers->get('X-Frame-Options'));
    }

    #[Test]
    public function securityHeadersSubscriberListensToResponseEvent(): void
    {
        $events = SecurityHeadersSubscriber::getSubscribedEvents();

        self::assertArrayHasKey('kernel.response', $events);
    }

    #[Test]
    public function userCheckerAllowsActiveUser(): void
    {
        $checker = new UserChecker();
        $user = new User('active@test.com');

        // Should not throw - user is active by default
        $checker->checkPreAuth($user);
        self::assertTrue(true); // If we get here, no exception was thrown
    }

    #[Test]
    public function userCheckerBlocksInactiveUser(): void
    {
        $checker = new UserChecker();
        $user = new User('inactive@test.com');

        // Deactivate user via reflection
        $ref = new \ReflectionClass($user);
        $prop = $ref->getProperty('isActive');
        $prop->setValue($user, false);

        self::expectException(CustomUserMessageAccountStatusException::class);

        $checker->checkPreAuth($user);
    }

    #[Test]
    public function userCheckerIgnoresNonUserInterface(): void
    {
        $checker = new UserChecker();
        $nonUser = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        // Should not throw for non-User instances
        $checker->checkPreAuth($nonUser);
        self::assertTrue(true);
    }
}
