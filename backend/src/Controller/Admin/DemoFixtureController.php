<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\DemoScenarioBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/fixtures')]
#[IsGranted('ROLE_ADMIN')]
class DemoFixtureController extends AbstractController
{
    public function __construct(
        private readonly DemoScenarioBuilder $scenarioBuilder,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_fixtures_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/fixtures/index.html.twig');
    }

    #[Route('/load', name: 'admin_fixtures_load', methods: ['POST'])]
    public function load(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('load-fixtures', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_fixtures_index');
        }

        $result = $this->scenarioBuilder->buildScenario(200);

        $this->em->persist($result->customer);
        $this->em->persist($result->warehouse);
        $this->em->persist($result->customerUser);

        foreach ($result->vehicles as $vehicle) {
            $this->em->persist($vehicle);
        }
        foreach ($result->drivers as $driver) {
            $this->em->persist($driver);
        }
        foreach ($result->shipments as $shipment) {
            $this->em->persist($shipment);
        }

        $this->em->flush();

        $this->addFlash('success', sprintf(
            'Datos demo cargados: %d envios, %d vehiculos, %d conductores.',
            \count($result->shipments),
            \count($result->vehicles),
            \count($result->drivers),
        ));

        return $this->redirectToRoute('admin_shipments_index');
    }
}
