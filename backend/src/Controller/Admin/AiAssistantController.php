<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\AiAssistantService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ai-assistant')]
#[IsGranted('ROLE_OPERATOR')]
class AiAssistantController extends AbstractController
{
    public function __construct(
        private readonly AiAssistantService $aiAssistantService,
    ) {}

    #[Route('', name: 'admin_ai_assistant', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/ai_assistant/index.html.twig');
    }

    #[Route('/message', name: 'admin_ai_assistant_message', methods: ['POST'])]
    public function message(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userMessage = trim((string) ($data['message'] ?? ''));

        if ($userMessage === '') {
            return $this->json(['error' => 'El mensaje no puede estar vacio.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($userMessage) > 2000) {
            return $this->json(['error' => 'El mensaje es demasiado largo (maximo 2000 caracteres).'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $customerId = $user->getCustomer()?->getId();

        $result = $this->aiAssistantService->chat($userMessage, $customerId, $user);

        return $this->json($result);
    }
}
