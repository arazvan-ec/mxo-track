<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'notification_index', methods: ['GET'])]
    public function index(
        Request $request,
        NotificationRepository $notificationRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $notifications = $notificationRepository->findPaginatedForUser($user, $limit, $offset);
        $totalCount = $notificationRepository->countForUser($user);
        $unreadCount = $notificationRepository->countUnreadForUser($user);
        $totalPages = max(1, (int) ceil($totalCount / $limit));

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
        ]);
    }

    #[Route('/notifications/{publicId}/read', name: 'notification_mark_read', methods: ['POST'])]
    public function markAsRead(
        string $publicId,
        NotificationRepository $notificationRepository,
        NotificationService $notificationService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $notification = $notificationRepository->findOneByPublicId($publicId);

        if ($notification === null || $notification->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Notificacion no encontrada.');
        }

        $notificationService->markAsRead($notification);

        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            return new JsonResponse(['ok' => true]);
        }

        return $this->redirectToRoute('notification_index');
    }

    #[Route('/api/notifications/unread-count', name: 'api_notification_unread_count', methods: ['GET'])]
    public function unreadCount(
        NotificationService $notificationService,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse([
            'unread_count' => $notificationService->getUnreadCount($user),
        ]);
    }
}
