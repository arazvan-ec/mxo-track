<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\RoutePlanTemplate;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/admin/route-planner/templates')]
#[IsGranted('ROLE_OPERATOR')]
class RouteTemplateController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * List all templates for the current user's customer.
     */
    #[SymfonyRoute('', name: 'admin_route_templates_list', methods: ['GET'], format: 'json')]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $customer = $user->getCustomer();

        $qb = $this->em->getRepository(RoutePlanTemplate::class)->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC');

        if ($customer !== null) {
            $qb->andWhere('t.customer = :customer')->setParameter('customer', $customer);
        }

        /** @var RoutePlanTemplate[] $templates */
        $templates = $qb->getQuery()->getResult();

        $result = [];
        foreach ($templates as $template) {
            $result[] = [
                'public_id' => $template->getPublicIdString(),
                'name' => $template->getName(),
                'stops_count' => \count($template->getTemplateData()),
                'created_at' => $template->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * Create a new route plan template.
     */
    #[SymfonyRoute('', name: 'admin_route_templates_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $customer = $user->getCustomer();

        if ($customer === null) {
            return new JsonResponse(['error' => 'Usuario sin cliente asignado.'], 400);
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'JSON invalido.'], 400);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return new JsonResponse(['error' => 'El nombre es obligatorio.'], 400);
        }

        if (mb_strlen($name) > 100) {
            return new JsonResponse(['error' => 'El nombre no puede superar 100 caracteres.'], 400);
        }

        /** @var array<int, array<string, mixed>> $stops */
        $stops = $payload['stops'] ?? [];
        if (!\is_array($stops) || \count($stops) === 0) {
            return new JsonResponse(['error' => 'Se requiere al menos una parada.'], 400);
        }

        $templateData = [];
        foreach ($stops as $i => $stop) {
            $templateData[] = [
                'address' => (string) ($stop['address'] ?? ''),
                'latitude' => isset($stop['latitude']) ? (float) $stop['latitude'] : null,
                'longitude' => isset($stop['longitude']) ? (float) $stop['longitude'] : null,
                'sequence' => isset($stop['sequence']) ? (int) $stop['sequence'] : $i + 1,
            ];
        }

        $template = new RoutePlanTemplate($name, $customer);
        $template->setTemplateData($templateData);

        $this->em->persist($template);
        $this->em->flush();

        return new JsonResponse([
            'ok' => true,
            'public_id' => $template->getPublicIdString(),
            'name' => $template->getName(),
            'stops_count' => \count($templateData),
        ], 201);
    }

    /**
     * Load a single template by public ID.
     */
    #[SymfonyRoute('/{publicId}', name: 'admin_route_templates_load', methods: ['GET'])]
    public function load(string $publicId): JsonResponse
    {
        $template = $this->em->getRepository(RoutePlanTemplate::class)->findOneBy([
            'publicId' => $publicId,
        ]);

        if (!$template instanceof RoutePlanTemplate) {
            return new JsonResponse(['error' => 'Plantilla no encontrada.'], 404);
        }

        return new JsonResponse([
            'public_id' => $template->getPublicIdString(),
            'name' => $template->getName(),
            'stops' => $template->getTemplateData(),
            'created_at' => $template->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete a template by public ID.
     */
    #[SymfonyRoute('/{publicId}', name: 'admin_route_templates_delete', methods: ['DELETE'])]
    public function delete(string $publicId): JsonResponse
    {
        $template = $this->em->getRepository(RoutePlanTemplate::class)->findOneBy([
            'publicId' => $publicId,
        ]);

        if (!$template instanceof RoutePlanTemplate) {
            return new JsonResponse(['error' => 'Plantilla no encontrada.'], 404);
        }

        $this->em->remove($template);
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    /**
     * Render the template management page.
     */
    #[SymfonyRoute('/manage', name: 'admin_route_templates_manage', methods: ['GET'], priority: 10)]
    public function manage(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $customer = $user->getCustomer();

        $qb = $this->em->getRepository(RoutePlanTemplate::class)->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC');

        if ($customer !== null) {
            $qb->andWhere('t.customer = :customer')->setParameter('customer', $customer);
        }

        /** @var RoutePlanTemplate[] $templates */
        $templates = $qb->getQuery()->getResult();

        return $this->render('admin/route_templates/manage.html.twig', [
            'templates' => $templates,
        ]);
    }
}
