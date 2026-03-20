<?php

declare(strict_types=1);

namespace App\Controller;

use DateTimeImmutable;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Realtime\RealtimePublisherInterface;
use App\Realtime\SseMessage;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/dev')]
final class AdminDevPushPositionController extends AbstractController
{
    #[Route('/push-position', name: 'admin_dev_push_position', methods: ['POST'])]
    public function __invoke(Request $request, RealtimePublisherInterface $publisher): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            /** @var array<string,mixed> $input */
            $input = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->json([
                'error' => 'invalid_json',
                'message' => 'Body JSON inválido.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $vehicleId = trim((string) ($input['vehicleId'] ?? ''));
        if ($vehicleId === '') {
            return $this->json([
                'error' => 'validation_error',
                'message' => 'vehicleId es obligatorio.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!isset($input['lat'], $input['lng'])) {
            return $this->json([
                'error' => 'validation_error',
                'message' => 'lat y lng son obligatorios.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $deviceTimeInput = (string) ($input['deviceTime'] ?? 'now');
        try {
            $deviceTime = new DateTimeImmutable($deviceTimeInput);
        } catch (\Throwable) {
            return $this->json([
                'error' => 'validation_error',
                'message' => 'deviceTime debe ser una fecha válida.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payload = [
            'vehicleId' => $vehicleId,
            'lat' => (float) $input['lat'],
            'lng' => (float) $input['lng'],
            'speed' => (float) ($input['speed'] ?? 0.0),
            'course' => (float) ($input['course'] ?? 0.0),
            'accuracy' => (float) ($input['accuracy'] ?? 0.0),
            'deviceTime' => $deviceTime->format(DATE_ATOM),
            'receivedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];

        $topic = sprintf('/vehicles/%s/position', $vehicleId);
        $publisher->publish(new SseMessage(data: $payload, topics: [$topic]));

        return $this->json([
            'ok' => true,
            'topic' => $topic,
            'payload' => $payload,
        ], Response::HTTP_ACCEPTED);
    }
}
