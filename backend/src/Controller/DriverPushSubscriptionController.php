<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Service\WebPushService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/driver/push-subscription')]
#[IsGranted('ROLE_DRIVER')]
class DriverPushSubscriptionController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    public function subscribe(
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => ['code' => 'invalid_json', 'message' => 'JSON inválido.']], 400);
        }

        $endpoint = $payload['endpoint'] ?? '';
        if ($endpoint === '' || !is_string($endpoint)) {
            return $this->json(['error' => ['code' => 'missing_endpoint', 'message' => 'El campo endpoint es obligatorio.']], 422);
        }

        /** @var User $driver */
        $driver = $this->getUser();
        $authKey = isset($payload['auth_key']) && is_string($payload['auth_key']) ? $payload['auth_key'] : null;
        $p256dhKey = isset($payload['p256dh_key']) && is_string($payload['p256dh_key']) ? $payload['p256dh_key'] : null;

        // Check if subscription already exists
        $existing = $em->getRepository(PushSubscription::class)->findOneBy([
            'user' => $driver,
            'endpoint' => $endpoint,
        ]);

        if ($existing instanceof PushSubscription) {
            return $this->json(['ok' => true, 'public_id' => $existing->getPublicIdString()], 200);
        }

        $subscription = new PushSubscription($driver, $endpoint, $authKey, $p256dhKey);
        $em->persist($subscription);
        $em->flush();

        return $this->json(['ok' => true, 'public_id' => $subscription->getPublicIdString()], 201);
    }

    #[Route('', methods: ['DELETE'])]
    public function unsubscribe(
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => ['code' => 'invalid_json', 'message' => 'JSON inválido.']], 400);
        }

        $endpoint = $payload['endpoint'] ?? '';
        if ($endpoint === '' || !is_string($endpoint)) {
            return $this->json(['error' => ['code' => 'missing_endpoint', 'message' => 'El campo endpoint es obligatorio.']], 422);
        }

        /** @var User $driver */
        $driver = $this->getUser();

        $subscription = $em->getRepository(PushSubscription::class)->findOneBy([
            'user' => $driver,
            'endpoint' => $endpoint,
        ]);

        if ($subscription instanceof PushSubscription) {
            $em->remove($subscription);
            $em->flush();
        }

        return $this->json(null, 204);
    }

    #[Route('/vapid-key', methods: ['GET'])]
    public function vapidKey(WebPushService $pushService): JsonResponse
    {
        return $this->json(['public_key' => $pushService->getVapidPublicKey()]);
    }
}
