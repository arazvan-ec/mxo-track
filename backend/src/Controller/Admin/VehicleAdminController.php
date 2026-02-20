<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Vehicle;
use App\Entity\VehicleLastPosition;
use App\Form\VehicleType;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/vehicles')]
class VehicleAdminController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VehicleRepository $vehicleRepository,
    ) {}

    #[Route('', name: 'admin_vehicles_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = self::ITEMS_PER_PAGE;

        $qb = $this->em->createQueryBuilder()
            ->select('v')
            ->from(Vehicle::class, 'v')
            ->orderBy('v.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $vehicles = $qb->getQuery()->getResult();

        $total = (int) $this->em->createQueryBuilder()
            ->select('COUNT(v.id)')
            ->from(Vehicle::class, 'v')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($total / $limit));

        // Fetch last positions for all vehicles in this page
        $lastPositions = [];
        if (\count($vehicles) > 0) {
            $positions = $this->em->getRepository(VehicleLastPosition::class)->findBy([
                'vehicle' => $vehicles,
            ]);
            foreach ($positions as $pos) {
                $lastPositions[$pos->getVehicle()->getId()] = $pos;
            }
        }

        return $this->render('admin/vehicle/index.html.twig', [
            'vehicles' => $vehicles,
            'lastPositions' => $lastPositions,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/new', name: 'admin_vehicles_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $vehicle = new Vehicle('');
        $form = $this->createForm(VehicleType::class, $vehicle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($vehicle);
            $this->em->flush();

            $this->addFlash('success', 'Vehiculo creado correctamente.');

            return $this->redirectToRoute('admin_vehicles_index');
        }

        return $this->render('admin/vehicle/form.html.twig', [
            'form' => $form,
            'vehicle' => $vehicle,
        ]);
    }

    #[Route('/{publicId}/edit', name: 'admin_vehicles_edit', methods: ['GET', 'POST'])]
    public function edit(string $publicId, Request $request): Response
    {
        $vehicle = $this->vehicleRepository->findOneByPublicId($publicId);

        if (!$vehicle instanceof Vehicle) {
            throw $this->createNotFoundException('Vehiculo no encontrado.');
        }

        $form = $this->createForm(VehicleType::class, $vehicle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Vehiculo actualizado correctamente.');

            return $this->redirectToRoute('admin_vehicles_index');
        }

        return $this->render('admin/vehicle/form.html.twig', [
            'form' => $form,
            'vehicle' => $vehicle,
        ]);
    }

    #[Route('/{publicId}/delete', name: 'admin_vehicles_delete', methods: ['POST'])]
    public function delete(string $publicId, Request $request): Response
    {
        $vehicle = $this->vehicleRepository->findOneByPublicId($publicId);

        if (!$vehicle instanceof Vehicle) {
            throw $this->createNotFoundException('Vehiculo no encontrado.');
        }

        if (!$this->isCsrfTokenValid('delete-vehicle-' . $publicId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_vehicles_index');
        }

        $vehicle->setActive(false);
        $this->em->flush();

        $this->addFlash('success', 'Vehiculo desactivado correctamente.');

        return $this->redirectToRoute('admin_vehicles_index');
    }
}
