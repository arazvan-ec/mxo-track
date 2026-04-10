<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Customer;
use App\Entity\ReoptimizationPolicy;
use App\Repository\ReoptimizationPolicyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_OPERATOR')]
#[Route('/api/admin/reoptimization-policies')]
final class ReoptimizationPolicyApiController
{
    public function __construct(
        private readonly ReoptimizationPolicyRepository $repo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'api_admin_reoptimization_policies_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $policies = $this->repo->findAll();

        return new JsonResponse(array_map($this->serialize(...), $policies));
    }

    #[Route('', name: 'api_admin_reoptimization_policies_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $customerRepo = $this->em->getRepository(Customer::class);
        $customer = $customerRepo->findOneBy(['publicId' => $data['customer_public_id']]);

        $policy = new ReoptimizationPolicy(
            customer: $customer,
            triggers: $data['triggers'] ?? [],
            delayThresholdMinutes: $data['delay_threshold_minutes'] ?? 30,
            cooldownMinutes: $data['cooldown_minutes'] ?? 10,
            enabled: $data['enabled'] ?? true,
        );
        $policy->initializePublicId();

        $this->em->persist($policy);
        $this->em->flush();

        return new JsonResponse($this->serialize($policy), 201);
    }

    #[Route('/{publicId}', name: 'api_admin_reoptimization_policies_update', methods: ['PUT'])]
    public function update(string $publicId, Request $request): JsonResponse
    {
        $policy = $this->repo->findOneBy(['publicId' => $publicId]);

        $data = json_decode($request->getContent(), true);

        if (isset($data['triggers'])) {
            $policy->setTriggers($data['triggers']);
        }
        if (isset($data['delay_threshold_minutes'])) {
            $policy->setDelayThresholdMinutes($data['delay_threshold_minutes']);
        }
        if (isset($data['cooldown_minutes'])) {
            $policy->setCooldownMinutes($data['cooldown_minutes']);
        }
        if (isset($data['consecutive_exception_threshold'])) {
            $policy->setConsecutiveExceptionThreshold($data['consecutive_exception_threshold']);
        }
        if (isset($data['enabled'])) {
            $policy->setEnabled($data['enabled']);
        }

        $this->em->flush();

        return new JsonResponse($this->serialize($policy));
    }

    #[Route('/{publicId}', name: 'api_admin_reoptimization_policies_delete', methods: ['DELETE'])]
    public function delete(string $publicId): JsonResponse
    {
        $policy = $this->repo->findOneBy(['publicId' => $publicId]);

        $this->em->remove($policy);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    private function serialize(ReoptimizationPolicy $p): array
    {
        $p->initializePublicId();

        return [
            'public_id' => $p->getPublicIdString(),
            'triggers' => $p->getTriggers(),
            'delay_threshold_minutes' => $p->getDelayThresholdMinutes(),
            'cooldown_minutes' => $p->getCooldownMinutes(),
            'consecutive_exception_threshold' => $p->getConsecutiveExceptionThreshold(),
            'enabled' => $p->isEnabled(),
        ];
    }
}
