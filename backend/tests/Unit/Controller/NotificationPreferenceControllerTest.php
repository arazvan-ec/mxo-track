<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\Api\NotificationPreferenceController;
use App\Entity\Customer;
use App\Entity\NotificationLog;
use App\Entity\NotificationPreference;
use App\Enum\NotificationChannel;
use App\Enum\NotificationLogStatus;
use App\Enum\NotificationTriggerType;
use App\Http\ApiErrorResponder;
use App\Provider\TenantContext;
use App\Repository\NotificationLogRepository;
use App\Repository\NotificationPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(NotificationPreferenceController::class)]
final class NotificationPreferenceControllerTest extends TestCase
{
    private TenantContext $tenantContext;
    private NotificationPreferenceRepository $prefRepo;
    private NotificationLogRepository $logRepo;
    private EntityManagerInterface $em;
    private ValidatorInterface $validator;
    private NotificationPreferenceController $controller;
    private Customer $customer;

    protected function setUp(): void
    {
        $this->tenantContext = $this->createMock(TenantContext::class);
        $this->prefRepo = $this->createMock(NotificationPreferenceRepository::class);
        $this->logRepo = $this->createMock(NotificationLogRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->customer = new Customer('Test Co');
        $this->tenantContext->method('getCustomer')->willReturn($this->customer);

        $this->controller = new NotificationPreferenceController(
            $this->tenantContext,
            $this->prefRepo,
            $this->logRepo,
            $this->em,
            $this->validator,
            new ApiErrorResponder(),
        );
    }

    #[Test]
    public function listReturnsPreferencesForCustomer(): void
    {
        $pref = new NotificationPreference(
            $this->customer,
            NotificationTriggerType::Reminder,
            NotificationChannel::Sms,
            true,
            'Custom template',
            ['hours_before' => 12],
        );
        $pref->initializePublicId();

        $this->prefRepo->method('findBy')->willReturn([$pref]);

        $response = $this->controller->list();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertCount(1, $data['preferences']);
        self::assertSame('reminder', $data['preferences'][0]['trigger_type']);
        self::assertSame('sms', $data['preferences'][0]['channel']);
    }

    #[Test]
    public function createPersistsNewPreference(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $persisted = null;
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted) {
            if ($entity instanceof NotificationPreference) {
                $entity->initializePublicId();
            }
            $persisted = $entity;
        });
        $this->em->expects(self::once())->method('flush');

        $request = new Request(content: json_encode([
            'trigger_type' => 'reminder',
            'channel' => 'sms',
            'enabled' => true,
            'message_template' => 'Hola {recipient_name}',
            'timing_config' => ['hours_before' => 6],
        ]));

        $response = $this->controller->create($request);

        self::assertSame(201, $response->getStatusCode());
        self::assertInstanceOf(NotificationPreference::class, $persisted);
        self::assertSame(NotificationTriggerType::Reminder, $persisted->getTriggerType());
        self::assertSame(NotificationChannel::Sms, $persisted->getChannel());
        self::assertSame('Hola {recipient_name}', $persisted->getMessageTemplate());
    }

    #[Test]
    public function createReturns422ForInvalidPayload(): void
    {
        $violation = $this->createMock(\Symfony\Component\Validator\ConstraintViolationInterface::class);
        $violation->method('getPropertyPath')->willReturn('triggerType');
        $violation->method('getMessage')->willReturn('This value should not be blank.');
        $violations = new ConstraintViolationList([$violation]);
        $this->validator->method('validate')->willReturn($violations);

        $request = new Request(content: json_encode(['trigger_type' => '', 'channel' => 'sms']));

        $response = $this->controller->create($request);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function deleteRemovesPreference(): void
    {
        $pref = new NotificationPreference(
            $this->customer,
            NotificationTriggerType::Reminder,
            NotificationChannel::Sms,
        );

        $this->prefRepo->method('findOneBy')->willReturn($pref);
        $this->em->expects(self::once())->method('remove')->with($pref);
        $this->em->expects(self::once())->method('flush');

        $response = $this->controller->delete('some-public-id');

        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function deleteReturns404ForMissingPreference(): void
    {
        $this->prefRepo->method('findOneBy')->willReturn(null);

        $response = $this->controller->delete('nonexistent-id');

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function logsReturnsPaginatedNotificationLogs(): void
    {
        $this->logRepo->method('findBy')->willReturn([]);
        $this->logRepo->method('count')->willReturn(0);

        $request = new Request(['page' => '1', 'limit' => '20']);
        $response = $this->controller->logs($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('logs', $data);
        self::assertArrayHasKey('total', $data);
    }
}
