<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Application\Delivery\DeliveryContext;
use App\Application\Delivery\DeliveryResult;
use App\Application\Delivery\DeliveryService;
use App\Application\Delivery\DriverConfirmationRequiredException;
use App\Application\Delivery\DriverNotOwnerException;
use App\Application\Delivery\ExceptionResult;
use App\Application\Delivery\StopNotFoundException;
use App\Application\Route\InspectionNotCompletedException;
use App\Application\Route\RouteLifecycleService;
use App\Application\Route\RouteNotFoundException;
use App\Application\Route\RouteNotOwnedException;
use App\Controller\DriverApiController;
use App\Dto\Driver\DeliverStopInput;
use App\Dto\Driver\ExceptionStopInput;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Entity\User;
use App\Enum\ExceptionCode;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Http\ApiErrorResponder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Tests the DriverApiController actions at the unit level by mocking all
 * dependencies. This avoids needing a running database and Symfony kernel
 * while still verifying controller behavior thoroughly.
 */
#[CoversClass(DriverApiController::class)]
final class DriverApiTest extends TestCase
{
    private ApiErrorResponder $errorResponder;

    protected function setUp(): void
    {
        $this->errorResponder = new ApiErrorResponder();
    }

    #[Test]
    public function deliverStopReturns201OnSuccess(): void
    {
        $driver = $this->createDriver();
        $route = $this->createRouteForDriver($driver);
        $stop = $this->createStopForRoute($route);

        $clientActionId = Uuid::v4()->toRfc4122();
        $payload = [
            'client_action_id' => $clientActionId,
            'signed_by_name' => 'John Doe',
            'recipient_id_encoded' => 'base64-encoded-recipient-id-data',
            'confirmed_by_driver' => true,
            'shipment_public_id' => null,
        ];

        $request = $this->createJsonRequest($payload);

        $deliveryService = $this->createMock(DeliveryService::class);
        $deliveryService->expects(self::once())
            ->method('deliverStop')
            ->willReturn(new DeliveryResult(idempotent: false, podPublicId: 'pod-123'));

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $controller = $this->createControllerWithUser($driver);

        $response = $controller->deliver(
            $stop->getPublicIdString(),
            $request,
            $deliveryService,
            $this->errorResponder,
            $validator,
        );

        self::assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertTrue($data['ok']);
        self::assertFalse($data['idempotent']);
    }

    #[Test]
    public function deliverStopReturnsIdempotentOnDuplicateAction(): void
    {
        $driver = $this->createDriver();
        $route = $this->createRouteForDriver($driver);
        $stop = $this->createStopForRoute($route);

        $clientActionId = Uuid::v4()->toRfc4122();
        $payload = [
            'client_action_id' => $clientActionId,
            'signed_by_name' => 'John Doe',
            'recipient_id_encoded' => 'base64-encoded-recipient-id-data',
            'confirmed_by_driver' => true,
        ];

        $request = $this->createJsonRequest($payload);

        $deliveryService = $this->createMock(DeliveryService::class);
        $deliveryService->expects(self::once())
            ->method('deliverStop')
            ->willReturn(new DeliveryResult(idempotent: true));

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $controller = $this->createControllerWithUser($driver);

        $response = $controller->deliver(
            $stop->getPublicIdString(),
            $request,
            $deliveryService,
            $this->errorResponder,
            $validator,
        );

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertTrue($data['ok']);
        self::assertTrue($data['idempotent']);
    }

    #[Test]
    public function deliverStopReturns404ForNonexistentStop(): void
    {
        $driver = $this->createDriver();

        $payload = [
            'client_action_id' => Uuid::v4()->toRfc4122(),
            'signed_by_name' => 'John Doe',
            'recipient_id_encoded' => 'base64-encoded-recipient-id-data',
            'confirmed_by_driver' => true,
        ];

        $request = $this->createJsonRequest($payload);

        $deliveryService = $this->createMock(DeliveryService::class);
        $deliveryService->expects(self::once())
            ->method('deliverStop')
            ->willThrowException(new StopNotFoundException('nonexistent-public-id'));

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $controller = $this->createControllerWithUser($driver);

        $response = $controller->deliver(
            'nonexistent-public-id',
            $request,
            $deliveryService,
            $this->errorResponder,
            $validator,
        );

        self::assertSame(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('stop_not_found', $data['error']['code']);
    }

    #[Test]
    public function exceptionStopReturns201OnSuccess(): void
    {
        $driver = $this->createDriver();
        $route = $this->createRouteForDriver($driver);
        $stop = $this->createStopForRoute($route);

        $clientActionId = Uuid::v4()->toRfc4122();
        $payload = [
            'client_action_id' => $clientActionId,
            'reason' => 'ABSENT',
            'comment' => 'Nobody home',
        ];

        $request = $this->createJsonRequest($payload);

        $deliveryService = $this->createMock(DeliveryService::class);
        $deliveryService->expects(self::once())
            ->method('reportException')
            ->willReturn(new ExceptionResult(idempotent: false));

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $controller = $this->createControllerWithUser($driver);

        $response = $controller->exception(
            $stop->getPublicIdString(),
            $request,
            $deliveryService,
            $this->errorResponder,
            $validator,
        );

        self::assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertTrue($data['ok']);
        self::assertFalse($data['idempotent']);
    }

    #[Test]
    public function exceptionStopReturnsIdempotentOnDuplicateAction(): void
    {
        $driver = $this->createDriver();
        $route = $this->createRouteForDriver($driver);
        $stop = $this->createStopForRoute($route);

        $payload = [
            'client_action_id' => Uuid::v4()->toRfc4122(),
            'reason' => 'ABSENT',
            'comment' => 'Nobody home',
        ];

        $request = $this->createJsonRequest($payload);

        $deliveryService = $this->createMock(DeliveryService::class);
        $deliveryService->expects(self::once())
            ->method('reportException')
            ->willReturn(new ExceptionResult(idempotent: true));

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $controller = $this->createControllerWithUser($driver);

        $response = $controller->exception(
            $stop->getPublicIdString(),
            $request,
            $deliveryService,
            $this->errorResponder,
            $validator,
        );

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertTrue($data['ok']);
        self::assertTrue($data['idempotent']);
    }

    #[Test]
    public function exceptionStopMarksStopWithExceptionStatus(): void
    {
        $driver = $this->createDriver();
        $route = $this->createRouteForDriver($driver);
        $stop = $this->createStopForRoute($route);

        self::assertSame(RouteStopStatus::PENDING, $stop->getStatus());

        $payload = [
            'client_action_id' => Uuid::v4()->toRfc4122(),
            'reason' => 'WRONG_ADDRESS',
            'comment' => 'Address does not exist',
        ];

        $request = $this->createJsonRequest($payload);

        // The DeliveryService now handles marking the stop internally.
        // We simulate the side effect by having the mock call markException on the stop.
        $deliveryService = $this->createMock(DeliveryService::class);
        $deliveryService->expects(self::once())
            ->method('reportException')
            ->willReturnCallback(function () use ($stop): ExceptionResult {
                $stop->markException(ExceptionCode::WRONG_ADDRESS, 'Address does not exist');
                return new ExceptionResult(idempotent: false);
            });

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $controller = $this->createControllerWithUser($driver);

        $controller->exception(
            $stop->getPublicIdString(),
            $request,
            $deliveryService,
            $this->errorResponder,
            $validator,
        );

        self::assertSame(RouteStopStatus::EXCEPTION, $stop->getStatus());
        self::assertSame(ExceptionCode::WRONG_ADDRESS, $stop->getExceptionCode());
        self::assertSame('Address does not exist', $stop->getExceptionNotes());
    }

    #[Test]
    public function routeStartTransitionsPlannedToActive(): void
    {
        $driver = $this->createDriver();
        $route = $this->createRouteForDriver($driver);

        self::assertSame(RouteStatus::PLANNED, $route->getStatus());

        $lifecycleService = $this->createMock(RouteLifecycleService::class);
        $lifecycleService->expects(self::once())
            ->method('startRoute')
            ->willReturnCallback(function () use ($route): Route {
                $route->start();
                return $route;
            });

        $controller = $this->createControllerWithUser($driver);

        $response = $controller->start(
            $route->getPublicIdString(),
            $lifecycleService,
            $this->errorResponder,
        );

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertTrue($data['ok']);
        self::assertSame('ACTIVE', $data['status']);
        self::assertSame(RouteStatus::ACTIVE, $route->getStatus());
    }

    #[Test]
    public function routeFinishTransitionsActiveToDone(): void
    {
        $driver = $this->createDriver();
        $route = $this->createRouteForDriver($driver);
        $route->start(); // PLANNED -> ACTIVE

        self::assertSame(RouteStatus::ACTIVE, $route->getStatus());

        $lifecycleService = $this->createMock(RouteLifecycleService::class);
        $lifecycleService->expects(self::once())
            ->method('finishRoute')
            ->willReturnCallback(function () use ($route): Route {
                $route->finish();
                return $route;
            });

        $controller = $this->createControllerWithUser($driver);

        $response = $controller->finish(
            $route->getPublicIdString(),
            $lifecycleService,
            $this->errorResponder,
        );

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertTrue($data['ok']);
        self::assertSame('DONE', $data['status']);
        self::assertSame(RouteStatus::DONE, $route->getStatus());
    }

    #[Test]
    public function routeStartReturns404ForNonexistentRoute(): void
    {
        $driver = $this->createDriver();

        $lifecycleService = $this->createMock(RouteLifecycleService::class);
        $lifecycleService->expects(self::once())
            ->method('startRoute')
            ->willThrowException(new RouteNotFoundException('nonexistent-route-id'));

        $controller = $this->createControllerWithUser($driver);

        $response = $controller->start(
            'nonexistent-route-id',
            $lifecycleService,
            $this->errorResponder,
        );

        self::assertSame(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('route_not_found', $data['error']['code']);
    }

    #[Test]
    public function routeStartReturns404ForRouteOwnedByDifferentDriver(): void
    {
        $driver = $this->createDriver();
        $otherDriver = $this->createDriverWithId('other@test.com', '999');

        $route = $this->createRouteForDriver($otherDriver);

        $lifecycleService = $this->createMock(RouteLifecycleService::class);
        $lifecycleService->expects(self::once())
            ->method('startRoute')
            ->willThrowException(new RouteNotOwnedException());

        $controller = $this->createControllerWithUser($driver);

        $response = $controller->start(
            $route->getPublicIdString(),
            $lifecycleService,
            $this->errorResponder,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function deliverStopReturns400ForInvalidJson(): void
    {
        $driver = $this->createDriver();

        // Send invalid JSON content
        $request = Request::create('/api/driver/stops/test/deliver', 'POST', [], [], [], [], 'not-json{{{');

        $deliveryService = $this->createMock(DeliveryService::class);
        $validator = $this->createMock(ValidatorInterface::class);

        $controller = $this->createControllerWithUser($driver);

        $response = $controller->deliver(
            'test-stop-id',
            $request,
            $deliveryService,
            $this->errorResponder,
            $validator,
        );

        self::assertSame(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('invalid_json', $data['error']['code']);
    }

    // --- Helper methods ---

    private function createDriver(): User
    {
        $driver = new User('driver@test.com');
        $driver->assignRole(\App\Enum\UserRole::DRIVER);
        $driver->initializePublicId();

        // Set a fake ID via reflection so identity checks work
        $ref = new \ReflectionProperty($driver, 'id');
        $ref->setValue($driver, '1');

        return $driver;
    }

    private function createDriverWithId(string $email, string $id): User
    {
        $driver = new User($email);
        $driver->assignRole(\App\Enum\UserRole::DRIVER);
        $driver->initializePublicId();

        $ref = new \ReflectionProperty($driver, 'id');
        $ref->setValue($driver, $id);

        return $driver;
    }

    private function createRouteForDriver(User $driver): Route
    {
        $route = new Route('Test Route');
        $route->initializePublicId();
        $route->setDriver($driver);

        return $route;
    }

    private function createStopForRoute(Route $route): RouteStop
    {
        $stop = new RouteStop($route, 1, 'Calle Mayor 1, Madrid');
        $stop->initializePublicId();

        // Set a fake ID so getId() returns something in audit logging
        $ref = new \ReflectionProperty($stop, 'id');
        $ref->setValue($stop, '100');

        return $stop;
    }

    private function createJsonRequest(array $payload): Request
    {
        return Request::create(
            '/api/driver/stops/test/deliver',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Creates a controller with the given user set as the authenticated user.
     *
     * We use an anonymous subclass to override getUser() since AbstractController::getUser()
     * depends on the container/security token which we do not have in a unit test.
     */
    private function createControllerWithUser(User $user): DriverApiController
    {
        return new class ($user) extends DriverApiController {
            public function __construct(private readonly User $testUser)
            {
            }

            protected function getUser(): ?User
            {
                return $this->testUser;
            }

            protected function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
            {
                return new JsonResponse($data, $status, $headers);
            }
        };
    }
}
