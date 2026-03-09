<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Api\CreateWebhookRequest;
use App\Entity\WebhookEndpoint;
use App\Http\ApiErrorResponder;
use App\Security\ApiKeyUser;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Webhooks')]
#[Route('/api/v1/webhooks', name: 'api_v1_webhooks_')]
class WebhookApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ApiErrorResponder $errorResponder,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[OA\Post(summary: 'Create a webhook endpoint', description: 'Registers a new webhook endpoint. A signing secret is generated and returned in the response.')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['url'],
            properties: [
                new OA\Property(property: 'url', type: 'string', format: 'uri', example: 'https://example.com/webhook'),
                new OA\Property(
                    property: 'events',
                    type: 'array',
                    items: new OA\Items(type: 'string', example: 'shipment.delivered'),
                    example: ['shipment.delivered', 'shipment.exception'],
                ),
            ],
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Webhook endpoint created',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'public_id', type: 'string'),
                new OA\Property(property: 'url', type: 'string', format: 'uri'),
                new OA\Property(property: 'events', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'secret', type: 'string', description: 'HMAC signing secret (only returned on creation)'),
                new OA\Property(property: 'is_active', type: 'boolean'),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
            ],
        ),
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation error (invalid URL or events)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'invalid_url'),
                new OA\Property(property: 'message', type: 'string', example: 'A valid URL is required.'),
            ],
        ),
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var ApiKeyUser $user */
        $user = $this->getUser();
        $customer = $user->getCustomer();

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return $this->errorResponder->badRequest('invalid_json', 'Request body must be valid JSON.');
        }

        $dto = CreateWebhookRequest::fromArray($body);
        $violations = $this->validator->validate($dto);

        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }

            return $this->errorResponder->badRequest('validation_error', implode(' ', $errors));
        }

        $secret = $dto->secret ?? bin2hex(random_bytes(32));

        $endpoint = new WebhookEndpoint($customer, $dto->url, $secret);
        $endpoint->setEvents($dto->events);

        $this->em->persist($endpoint);
        $this->em->flush();

        return new JsonResponse([
            'public_id' => $endpoint->getPublicIdString(),
            'url' => $endpoint->getUrl(),
            'events' => $endpoint->getEvents(),
            'secret' => $secret,
            'is_active' => $endpoint->isActive(),
            'created_at' => $endpoint->getCreatedAt()->format(\DATE_ATOM),
        ], Response::HTTP_CREATED);
    }

    #[OA\Get(summary: 'List webhook endpoints', description: 'Returns all webhook endpoints for the authenticated customer.')]
    #[OA\Response(
        response: 200,
        description: 'List of webhook endpoints',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(properties: [
                        new OA\Property(property: 'public_id', type: 'string'),
                        new OA\Property(property: 'url', type: 'string', format: 'uri'),
                        new OA\Property(property: 'events', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'is_active', type: 'boolean'),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                    ]),
                ),
            ],
        ),
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $endpoints = $this->em->getRepository(WebhookEndpoint::class)->findBy(
            [],
            ['id' => 'DESC'],
        );

        $items = array_map(static fn (WebhookEndpoint $e): array => [
            'public_id' => $e->getPublicIdString(),
            'url' => $e->getUrl(),
            'events' => $e->getEvents(),
            'is_active' => $e->isActive(),
            'created_at' => $e->getCreatedAt()->format(\DATE_ATOM),
        ], $endpoints);

        return new JsonResponse(['items' => $items]);
    }

    #[OA\Delete(summary: 'Delete a webhook endpoint', description: 'Permanently removes a webhook endpoint.')]
    #[OA\Parameter(name: 'publicId', in: 'path', required: true, description: 'Webhook endpoint ULID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 204, description: 'Webhook endpoint deleted')]
    #[OA\Response(
        response: 404,
        description: 'Webhook endpoint not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'webhook_not_found'),
                new OA\Property(property: 'message', type: 'string', example: 'Webhook endpoint not found.'),
            ],
        ),
    )]
    #[Route('/{publicId}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $publicId): JsonResponse
    {
        try {
            $endpoint = $this->em->getRepository(WebhookEndpoint::class)->findOneBy([
                'publicId' => Ulid::fromString($publicId),
            ]);
        } catch (\Throwable) {
            $endpoint = null;
        }

        if (!$endpoint instanceof WebhookEndpoint) {
            return $this->errorResponder->notFound('webhook_not_found', 'Webhook endpoint not found.');
        }

        $this->em->remove($endpoint);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
