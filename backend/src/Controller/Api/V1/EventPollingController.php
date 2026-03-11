<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Provider\TenantContext;
use App\Repository\RealtimeEventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
final class EventPollingController extends AbstractController
{
    #[Route('/events', name: 'api_v1_events', methods: ['GET'])]
    public function poll(
        Request $request,
        RealtimeEventRepository $repository,
        TenantContext $tenantContext,
    ): JsonResponse {
        $customer = $tenantContext->getCustomer();
        if ($customer === null) {
            return new JsonResponse(['error' => 'No tenant context'], 403);
        }

        $sinceStr = $request->query->get('since');
        $since = $sinceStr !== null
            ? new \DateTimeImmutable($sinceStr)
            : new \DateTimeImmutable('-5 minutes');

        $topic = $request->query->getString('topic') ?: null;

        $events = $repository->findSince($customer, $topic, $since);

        $result = [];
        foreach ($events as $event) {
            $result[] = [
                'topic' => $event->getTopic(),
                'data' => $event->getData(),
                'type' => $event->getEventType(),
                'created_at' => $event->getCreatedAt()->format(\DATE_ATOM),
            ];
        }

        return new JsonResponse($result);
    }
}
