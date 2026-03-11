<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Entity\CustomerIntegration;
use App\Form\CustomerIntegrationType;
use App\Provider\ServiceType;
use App\Repository\CustomerIntegrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

#[Route('/admin/integrations')]
#[IsGranted('ROLE_ADMIN')]
class CustomerIntegrationAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustomerIntegrationRepository $repository,
    ) {
    }

    #[Route('', name: 'admin_integrations_index', methods: ['GET'])]
    public function index(): Response
    {
        $integrations = $this->em->createQueryBuilder()
            ->select('ci', 'c')
            ->from(CustomerIntegration::class, 'ci')
            ->join('ci.customer', 'c')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('ci.serviceType', 'ASC')
            ->addOrderBy('ci.priority', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/integration/index.html.twig', [
            'integrations' => $integrations,
        ]);
    }

    #[Route('/new', name: 'admin_integrations_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        // Use a dummy integration for form binding (enabled/priority fields)
        $dummy = new CustomerIntegration(
            new Customer(''),
            ServiceType::RouteOptimizer,
            'placeholder',
        );

        $form = $this->createForm(CustomerIntegrationType::class, $dummy, [
            'is_edit' => false,
            'config_json' => '{}',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $customer = $form->get('customer')->getData();
            $serviceType = ServiceType::from($form->get('serviceType')->getData());
            $providerType = $form->get('providerType')->getData();
            $configJson = $form->get('configJson')->getData() ?: '{}';

            $config = json_decode($configJson, true);
            if (!is_array($config)) {
                $this->addFlash('error', 'JSON de configuracion invalido.');

                return $this->render('admin/integration/form.html.twig', [
                    'form' => $form,
                    'integration' => null,
                ]);
            }

            $integration = new CustomerIntegration(
                $customer,
                $serviceType,
                $providerType,
                $config,
                $dummy->isEnabled(),
                $dummy->getPriority(),
            );

            $this->em->persist($integration);
            $this->em->flush();

            $this->addFlash('success', 'Integracion creada correctamente.');

            return $this->redirectToRoute('admin_integrations_index');
        }

        return $this->render('admin/integration/form.html.twig', [
            'form' => $form,
            'integration' => null,
        ]);
    }

    #[Route('/{publicId}/edit', name: 'admin_integrations_edit', methods: ['GET', 'POST'])]
    public function edit(string $publicId, Request $request): Response
    {
        $integration = $this->findByPublicId($publicId);

        if (!$integration instanceof CustomerIntegration) {
            throw $this->createNotFoundException('Integracion no encontrada.');
        }

        $form = $this->createForm(CustomerIntegrationType::class, $integration, [
            'is_edit' => true,
            'config_json' => json_encode($integration->getConfig(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $configJson = $form->get('configJson')->getData() ?: '{}';
            $config = json_decode($configJson, true);

            if (!is_array($config)) {
                $this->addFlash('error', 'JSON de configuracion invalido.');

                return $this->render('admin/integration/form.html.twig', [
                    'form' => $form,
                    'integration' => $integration,
                ]);
            }

            $integration->setConfig($config);
            $this->em->flush();

            $this->addFlash('success', 'Integracion actualizada correctamente.');

            return $this->redirectToRoute('admin_integrations_index');
        }

        return $this->render('admin/integration/form.html.twig', [
            'form' => $form,
            'integration' => $integration,
        ]);
    }

    #[Route('/{publicId}/delete', name: 'admin_integrations_delete', methods: ['POST'])]
    public function delete(string $publicId, Request $request): Response
    {
        $integration = $this->findByPublicId($publicId);

        if (!$integration instanceof CustomerIntegration) {
            throw $this->createNotFoundException('Integracion no encontrada.');
        }

        if (!$this->isCsrfTokenValid('delete-integration-' . $publicId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_integrations_index');
        }

        $this->em->remove($integration);
        $this->em->flush();

        $this->addFlash('success', 'Integracion eliminada correctamente.');

        return $this->redirectToRoute('admin_integrations_index');
    }

    private function findByPublicId(string $publicId): ?CustomerIntegration
    {
        try {
            return $this->repository->findOneBy([
                'publicId' => Ulid::fromString($publicId),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}
