<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\Admin\AiAssistantController;
use App\Entity\Customer;
use App\Entity\User;
use App\Service\AiAssistantService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(AiAssistantController::class)]
final class AiAssistantControllerTest extends TestCase
{
    private AiAssistantService&MockObject $aiService;
    private AiAssistantController $controller;

    protected function setUp(): void
    {
        $this->aiService = $this->createMock(AiAssistantService::class);
        $this->controller = new AiAssistantController($this->aiService);

        // AiAssistantController extends AbstractController, which requires a container.
        // For testing the message() method directly, we need to set up minimal security.
        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);

        $user = $this->createMock(User::class);
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('1');
        $user->method('getCustomer')->willReturn($customer);

        $tokenStorage = $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class);
        $token = $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $tokenStorage->method('getToken')->willReturn($token);

        $container->method('has')->willReturnCallback(fn (string $id): bool => match ($id) {
            'security.token_storage' => true,
            default => false,
        });
        $container->method('get')->willReturnCallback(fn (string $id) => match ($id) {
            'security.token_storage' => $tokenStorage,
            default => null,
        });

        $this->controller->setContainer($container);
    }

    #[Test]
    public function messageReturnsSuccessForValidInput(): void
    {
        $this->aiService->method('chat')
            ->willReturn([
                'response' => 'Hay 3 entregas pendientes.',
                'tools_used' => ['search_shipments'],
            ]);

        $request = new Request(content: json_encode(['message' => 'Cuantas entregas hay?']));

        $response = $this->controller->message($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('Hay 3 entregas pendientes.', $data['response']);
    }

    #[Test]
    public function messageReturnsErrorForEmptyMessage(): void
    {
        $request = new Request(content: json_encode(['message' => '']));

        $response = $this->controller->message($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }

    #[Test]
    public function messageReturnsErrorForTooLongMessage(): void
    {
        $longMessage = str_repeat('a', 2001);
        $request = new Request(content: json_encode(['message' => $longMessage]));

        $response = $this->controller->message($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('largo', $data['error']);
    }

    #[Test]
    public function messageReturnsErrorForMissingMessage(): void
    {
        $request = new Request(content: json_encode([]));

        $response = $this->controller->message($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function messageShowsUnavailableWhenResponseEmpty(): void
    {
        $this->aiService->method('chat')
            ->willReturn(['response' => '', 'tools_used' => []]);

        $request = new Request(content: json_encode(['message' => 'test']));

        $response = $this->controller->message($request);

        $data = json_decode($response->getContent(), true);
        self::assertStringContainsString('no esta disponible', $data['response']);
    }
}
