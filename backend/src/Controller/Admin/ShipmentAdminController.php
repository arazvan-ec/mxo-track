<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Entity\Shipment;
use App\Form\ShipmentType;
use App\Repository\ShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/shipments')]
#[IsGranted('ROLE_ADMIN')]
class ShipmentAdminController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShipmentRepository $shipmentRepository,
    ) {}

    #[Route('', name: 'admin_shipments_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = self::ITEMS_PER_PAGE;
        $customerFilter = $request->query->getString('customer', '');

        $qb = $this->em->createQueryBuilder()
            ->select('s')
            ->from(Shipment::class, 's')
            ->join('s.customer', 'c')
            ->where('s.deletedAt IS NULL')
            ->orderBy('s.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Shipment::class, 's')
            ->where('s.deletedAt IS NULL');

        if ($customerFilter !== '') {
            $customer = $this->em->getRepository(Customer::class)->findOneBy(['publicId' => $customerFilter]);
            if ($customer !== null) {
                $qb->andWhere('s.customer = :customer')->setParameter('customer', $customer);
                $countQb->andWhere('s.customer = :customer')->setParameter('customer', $customer);
            }
        }

        $shipments = $qb->getQuery()->getResult();
        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($total / $limit));

        $customers = $this->em->getRepository(Customer::class)->findBy([], ['name' => 'ASC']);

        return $this->render('admin/shipment/index.html.twig', [
            'shipments' => $shipments,
            'customers' => $customers,
            'customerFilter' => $customerFilter,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'admin_shipments_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $customer = $this->em->getRepository(Customer::class)->findOneBy([], ['name' => 'ASC']);
        if ($customer === null) {
            $this->addFlash('error', 'Debes crear un cliente antes de crear envios.');

            return $this->redirectToRoute('admin_shipments_index');
        }

        $shipment = new Shipment('', $customer);
        $form = $this->createForm(ShipmentType::class, $shipment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($shipment);
            $this->em->flush();

            $this->addFlash('success', 'Envio creado correctamente.');

            return $this->redirectToRoute('admin_shipments_index');
        }

        return $this->render('admin/shipment/form.html.twig', [
            'form' => $form,
            'shipment' => $shipment,
        ]);
    }

    #[Route('/{publicId}/edit', name: 'admin_shipments_edit', methods: ['GET', 'POST'])]
    public function edit(string $publicId, Request $request): Response
    {
        $shipment = $this->shipmentRepository->findOneByPublicId($publicId);

        if (!$shipment instanceof Shipment) {
            throw $this->createNotFoundException('Envio no encontrado.');
        }

        $form = $this->createForm(ShipmentType::class, $shipment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Envio actualizado correctamente.');

            return $this->redirectToRoute('admin_shipments_index');
        }

        return $this->render('admin/shipment/form.html.twig', [
            'form' => $form,
            'shipment' => $shipment,
        ]);
    }

    #[Route('/{publicId}/delete', name: 'admin_shipments_delete', methods: ['POST'])]
    public function delete(string $publicId, Request $request): Response
    {
        $shipment = $this->shipmentRepository->findOneByPublicId($publicId);

        if (!$shipment instanceof Shipment) {
            throw $this->createNotFoundException('Envio no encontrado.');
        }

        if (!$this->isCsrfTokenValid('delete-shipment-' . $publicId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_shipments_index');
        }

        $shipment->softDelete();
        $this->em->flush();

        $this->addFlash('success', 'Envio eliminado correctamente.');

        return $this->redirectToRoute('admin_shipments_index');
    }
}
