<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Entity\CustomerVehicle;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Form\CustomerType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Ulid;

#[Route('/admin/customers')]
class CustomerAdminController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_customers_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = self::ITEMS_PER_PAGE;

        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->orderBy('c.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $customers = $qb->getQuery()->getResult();

        $total = (int) $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Customer::class, 'c')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($total / $limit));

        // Count vehicles per customer
        $vehicleCounts = [];
        if (\count($customers) > 0) {
            $customerIds = array_map(static fn (Customer $c) => $c->getId(), $customers);

            $vehicleCountRows = $this->em->createQueryBuilder()
                ->select('IDENTITY(cv.customer) AS customer_id, COUNT(cv.id) AS vehicle_count')
                ->from(CustomerVehicle::class, 'cv')
                ->where('cv.customer IN (:ids)')
                ->setParameter('ids', $customerIds)
                ->groupBy('cv.customer')
                ->getQuery()
                ->getArrayResult();

            foreach ($vehicleCountRows as $row) {
                $vehicleCounts[$row['customer_id']] = (int) $row['vehicle_count'];
            }
        }

        // Count users per customer
        $userCounts = [];
        if (\count($customers) > 0) {
            $customerIds = array_map(static fn (Customer $c) => $c->getId(), $customers);

            $userCountRows = $this->em->createQueryBuilder()
                ->select('IDENTITY(u.customer) AS customer_id, COUNT(u.id) AS user_count')
                ->from(User::class, 'u')
                ->where('u.customer IN (:ids)')
                ->setParameter('ids', $customerIds)
                ->groupBy('u.customer')
                ->getQuery()
                ->getArrayResult();

            foreach ($userCountRows as $row) {
                $userCounts[$row['customer_id']] = (int) $row['user_count'];
            }
        }

        return $this->render('admin/customer/index.html.twig', [
            'customers' => $customers,
            'page' => $page,
            'totalPages' => $totalPages,
            'vehicleCounts' => $vehicleCounts,
            'userCounts' => $userCounts,
        ]);
    }

    #[Route('/new', name: 'admin_customers_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $customer = new Customer('');
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($customer);
            $this->em->flush();

            $this->addFlash('success', 'Almacen creado correctamente.');

            return $this->redirectToRoute('admin_customers_index');
        }

        return $this->render('admin/customer/form.html.twig', [
            'form' => $form,
            'customer' => $customer,
        ]);
    }

    #[Route('/{publicId}/edit', name: 'admin_customers_edit', methods: ['GET', 'POST'])]
    public function edit(string $publicId, Request $request): Response
    {
        $customer = $this->findCustomerByPublicId($publicId);

        if (!$customer instanceof Customer) {
            throw $this->createNotFoundException('Almacen no encontrado.');
        }

        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Almacen actualizado correctamente.');

            return $this->redirectToRoute('admin_customers_index');
        }

        return $this->render('admin/customer/form.html.twig', [
            'form' => $form,
            'customer' => $customer,
        ]);
    }

    #[Route('/{publicId}/delete', name: 'admin_customers_delete', methods: ['POST'])]
    public function delete(string $publicId, Request $request): Response
    {
        $customer = $this->findCustomerByPublicId($publicId);

        if (!$customer instanceof Customer) {
            throw $this->createNotFoundException('Almacen no encontrado.');
        }

        if (!$this->isCsrfTokenValid('delete-customer-' . $publicId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_customers_index');
        }

        $customer->setActive(false);
        $this->em->flush();

        $this->addFlash('success', 'Almacen desactivado correctamente.');

        return $this->redirectToRoute('admin_customers_index');
    }

    #[Route('/{publicId}/vehicles', name: 'admin_customers_vehicles', methods: ['GET', 'POST'])]
    public function vehicles(string $publicId, Request $request): Response
    {
        $customer = $this->findCustomerByPublicId($publicId);

        if (!$customer instanceof Customer) {
            throw $this->createNotFoundException('Almacen no encontrado.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('vehicles-customer-' . $publicId, $request->request->getString('_token'))) {
                $this->addFlash('error', 'Token CSRF invalido.');

                return $this->redirectToRoute('admin_customers_vehicles', ['publicId' => $publicId]);
            }

            $selectedIds = $request->request->all('vehicle_ids');

            // Remove existing assignments
            $this->em->createQueryBuilder()
                ->delete(CustomerVehicle::class, 'cv')
                ->where('cv.customer = :c')
                ->setParameter('c', $customer)
                ->getQuery()
                ->execute();

            // Add selected
            foreach ($selectedIds as $vid) {
                $vehicle = $this->em->find(Vehicle::class, $vid);
                if ($vehicle) {
                    $this->em->persist(new CustomerVehicle($customer, $vehicle));
                }
            }

            $this->em->flush();

            $this->addFlash('success', 'Vehiculos asignados correctamente.');

            return $this->redirectToRoute('admin_customers_vehicles', ['publicId' => $publicId]);
        }

        // Get all active vehicles
        $vehicles = $this->em->createQueryBuilder()
            ->select('v')
            ->from(Vehicle::class, 'v')
            ->where('v.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('v.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Get currently assigned vehicle IDs
        $assignedRows = $this->em->createQueryBuilder()
            ->select('IDENTITY(cv.vehicle) AS vehicle_id')
            ->from(CustomerVehicle::class, 'cv')
            ->where('cv.customer = :c')
            ->setParameter('c', $customer)
            ->getQuery()
            ->getArrayResult();

        $assignedIds = array_map(static fn (array $row) => $row['vehicle_id'], $assignedRows);

        return $this->render('admin/customer/vehicles.html.twig', [
            'customer' => $customer,
            'vehicles' => $vehicles,
            'assignedIds' => $assignedIds,
        ]);
    }

    private function findCustomerByPublicId(string $publicId): ?Customer
    {
        try {
            return $this->em->getRepository(Customer::class)->findOneBy([
                'publicId' => Ulid::fromString($publicId),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}
