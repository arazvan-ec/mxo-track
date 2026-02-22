<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository,
        private readonly HubInterface $hub,
    ) {
    }

    public function notify(User $user, string $type, string $title, string $message, array $payload = []): void
    {
        $notification = new Notification(
            $user,
            $type,
            $title,
            $message,
            'in_app',
            $payload !== [] ? $payload : null,
        );

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $this->publishMercureUpdate($user);
    }

    public function notifyCustomerUsers(Customer $customer, string $type, string $title, string $message): void
    {
        $users = $this->entityManager->getRepository(User::class)->findBy(['customer' => $customer]);

        foreach ($users as $user) {
            $notification = new Notification($user, $type, $title, $message);
            $this->entityManager->persist($notification);
        }

        $this->entityManager->flush();

        foreach ($users as $user) {
            $this->publishMercureUpdate($user);
        }
    }

    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
        $this->entityManager->flush();

        $this->publishMercureUpdate($notification->getUser());
    }

    public function getUnreadCount(User $user): int
    {
        return $this->notificationRepository->countUnreadForUser($user);
    }

    private function publishMercureUpdate(User $user): void
    {
        try {
            $unreadCount = $this->notificationRepository->countUnreadForUser($user);
            $topic = sprintf('/users/%s/notifications', $user->getId());

            $this->hub->publish(new Update(
                $topic,
                (string) json_encode([
                    'type' => 'notification_count',
                    'unread_count' => $unreadCount,
                ], JSON_THROW_ON_ERROR),
            ));
        } catch (\Throwable) {
            // Mercure publish failure should not break the notification flow
        }
    }
}
