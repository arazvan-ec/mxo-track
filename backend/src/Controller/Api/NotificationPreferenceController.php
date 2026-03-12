<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\NotificationPreferenceDto;
use App\Entity\NotificationPreference;
use App\Enum\NotificationChannel;
use App\Enum\NotificationTriggerType;
use App\Http\ApiErrorResponder;
use App\Provider\TenantContext;
use App\Repository\NotificationLogRepository;
use App\Repository\NotificationPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_CUSTOMER')]
#[Route('/api/notification-preferences', name: 'api_notification_preferences_')]
class NotificationPreferenceController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly NotificationPreferenceRepository $prefRepo,
        private readonly NotificationLogRepository $logRepo,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly ApiErrorResponder $errorResponder,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $customer = $this->tenantContext->getCustomer();

        $preferences = $this->prefRepo->findBy(['customer' => $customer]);

        return new JsonResponse([
            'preferences' => array_map(fn (NotificationPreference $p) => [
                'public_id' => (string) $p->getPublicId(),
                'trigger_type' => $p->getTriggerType()->value,
                'channel' => $p->getChannel()->value,
                'enabled' => $p->isEnabled(),
                'message_template' => $p->getMessageTemplate(),
                'timing_config' => $p->getTimingConfig(),
            ], $preferences),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $customer = $this->tenantContext->getCustomer();

        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            return $this->errorResponder->badRequest('invalid_json', 'JSON invalido.');
        }

        $dto = NotificationPreferenceDto::fromArray($body);
        $violations = $this->validator->validate($dto);

        if ($violations->count() > 0) {
            return $this->errorResponder->unprocessableEntity('validation_error', $violations);
        }

        $triggerType = NotificationTriggerType::from($dto->triggerType);
        $channel = NotificationChannel::from($dto->channel);

        $preference = new NotificationPreference(
            customer: $customer,
            triggerType: $triggerType,
            channel: $channel,
            enabled: $dto->enabled,
            messageTemplate: $dto->messageTemplate,
            timingConfig: $dto->timingConfig,
        );

        $this->em->persist($preference);
        $this->em->flush();

        return new JsonResponse([
            'public_id' => (string) $preference->getPublicId(),
            'trigger_type' => $preference->getTriggerType()->value,
            'channel' => $preference->getChannel()->value,
            'enabled' => $preference->isEnabled(),
            'message_template' => $preference->getMessageTemplate(),
            'timing_config' => $preference->getTimingConfig(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{publicId}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $publicId): JsonResponse
    {
        $customer = $this->tenantContext->getCustomer();

        $preference = $this->prefRepo->findOneBy([
            'publicId' => $publicId,
            'customer' => $customer,
        ]);

        if ($preference === null) {
            return $this->errorResponder->notFound('not_found', 'Preferencia no encontrada.');
        }

        $this->em->remove($preference);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/logs', name: 'logs', methods: ['GET'], priority: 10)]
    public function logs(Request $request): JsonResponse
    {
        $customer = $this->tenantContext->getCustomer();

        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $offset = ($page - 1) * $limit;

        $logs = $this->logRepo->findBy(
            ['customer' => $customer],
            ['createdAt' => 'DESC'],
            $limit,
            $offset,
        );

        $total = $this->logRepo->count(['customer' => $customer]);

        return new JsonResponse([
            'logs' => array_map(fn ($log) => [
                'public_id' => (string) $log->getPublicId(),
                'trigger_type' => $log->getTriggerType()->value,
                'channel' => $log->getChannel()->value,
                'recipient_phone' => $log->getRecipientPhone(),
                'status' => $log->getStatus()->value,
                'created_at' => $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ], $logs),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }
}
