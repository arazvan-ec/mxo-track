<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\UpdateUserPreferencesDto;
use App\Entity\User;
use App\Entity\UserPreference;
use App\Http\ApiErrorResponder;
use App\Repository\UserPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_USER')]
#[Route('/api/me/preferences', name: 'api_me_preferences_')]
class UserPreferenceController
{
    public function __construct(
        private readonly UserPreferenceRepository $prefRepo,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly ApiErrorResponder $errorResponder,
    ) {}

    #[Route('', name: 'get', methods: ['GET'])]
    public function get(#[CurrentUser] User $user): JsonResponse
    {
        $pref = $this->prefRepo->findOneByUser($user);

        if ($pref === null) {
            $pref = new UserPreference($user);
            $this->em->persist($pref);
            $this->em->flush();
        }

        return new JsonResponse($this->serialize($pref));
    }

    #[Route('', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            return $this->errorResponder->badRequest('invalid_json', 'JSON invalido.');
        }

        $dto = UpdateUserPreferencesDto::fromArray($body);
        $violations = $this->validator->validate($dto);

        if ($violations->count() > 0) {
            return $this->errorResponder->unprocessableEntity('validation_error', $violations);
        }

        $pref = $this->prefRepo->findOneByUser($user);

        if ($pref === null) {
            $pref = new UserPreference($user, $dto->widgetDefaultMode);
            $this->em->persist($pref);
        } else {
            $pref->setWidgetDefaultMode($dto->widgetDefaultMode);
        }

        $this->em->flush();

        return new JsonResponse($this->serialize($pref));
    }

    /**
     * @return array<string, string>
     */
    private function serialize(UserPreference $pref): array
    {
        return [
            'widget_default_mode' => $pref->getWidgetDefaultMode(),
        ];
    }
}
