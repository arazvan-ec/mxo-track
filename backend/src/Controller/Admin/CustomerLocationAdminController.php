<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Form\CustomerLocationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

#[Route('/admin/customers/{customerPublicId}/locations')]
#[IsGranted('ROLE_ADMIN')]
class CustomerLocationAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_customer_locations_index', methods: ['GET'])]
    public function index(string $customerPublicId): Response
    {
        $customer = $this->findCustomer($customerPublicId);

        $locations = $this->em->createQueryBuilder()
            ->select('l')
            ->from(CustomerLocation::class, 'l')
            ->where('l.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('l.isDefault', 'DESC')
            ->addOrderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/customer/locations/index.html.twig', [
            'customer' => $customer,
            'locations' => $locations,
        ]);
    }

    #[Route('/new', name: 'admin_customer_locations_new', methods: ['GET', 'POST'])]
    public function new(string $customerPublicId, Request $request): Response
    {
        $customer = $this->findCustomer($customerPublicId);

        $form = $this->createForm(CustomerLocationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $location = new CustomerLocation($customer, $data['name'], $data['address']);

            if ($data['latitude'] !== null) {
                $location->setLatitude((float) $data['latitude']);
            }
            if ($data['longitude'] !== null) {
                $location->setLongitude((float) $data['longitude']);
            }
            if ($data['isDefault'] ?? false) {
                $this->clearDefaultForCustomer($customer);
                $location->setDefault(true);
            }
            if (isset($data['isActive'])) {
                $location->setActive((bool) $data['isActive']);
            }

            $this->em->persist($location);
            $this->em->flush();

            $this->addFlash('success', 'Ubicacion creada correctamente.');

            return $this->redirectToRoute('admin_customer_locations_index', [
                'customerPublicId' => $customerPublicId,
            ]);
        }

        return $this->render('admin/customer/locations/form.html.twig', [
            'customer' => $customer,
            'form' => $form,
            'location' => null,
        ]);
    }

    #[Route('/{publicId}/edit', name: 'admin_customer_locations_edit', methods: ['GET', 'POST'])]
    public function edit(string $customerPublicId, string $publicId, Request $request): Response
    {
        $customer = $this->findCustomer($customerPublicId);
        $location = $this->findLocation($publicId, $customer);

        $form = $this->createForm(CustomerLocationType::class, [
            'name' => $location->getName(),
            'address' => $location->getAddress(),
            'latitude' => $location->getLatitude(),
            'longitude' => $location->getLongitude(),
            'isDefault' => $location->isDefault(),
            'isActive' => $location->isActive(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $location->setName($data['name']);
            $location->setAddress($data['address']);
            $location->setLatitude($data['latitude'] !== null ? (float) $data['latitude'] : null);
            $location->setLongitude($data['longitude'] !== null ? (float) $data['longitude'] : null);
            $location->setActive((bool) ($data['isActive'] ?? true));

            if ($data['isDefault'] ?? false) {
                $this->clearDefaultForCustomer($customer);
                $location->setDefault(true);
            } else {
                $location->setDefault(false);
            }

            $this->em->flush();

            $this->addFlash('success', 'Ubicacion actualizada correctamente.');

            return $this->redirectToRoute('admin_customer_locations_index', [
                'customerPublicId' => $customerPublicId,
            ]);
        }

        return $this->render('admin/customer/locations/form.html.twig', [
            'customer' => $customer,
            'form' => $form,
            'location' => $location,
        ]);
    }

    #[Route('/{publicId}/delete', name: 'admin_customer_locations_delete', methods: ['POST'])]
    public function delete(string $customerPublicId, string $publicId, Request $request): Response
    {
        $customer = $this->findCustomer($customerPublicId);
        $location = $this->findLocation($publicId, $customer);

        if (!$this->isCsrfTokenValid('delete-location-' . $publicId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_customer_locations_index', [
                'customerPublicId' => $customerPublicId,
            ]);
        }

        $this->em->remove($location);
        $this->em->flush();

        $this->addFlash('success', 'Ubicacion eliminada correctamente.');

        return $this->redirectToRoute('admin_customer_locations_index', [
            'customerPublicId' => $customerPublicId,
        ]);
    }

    private function findCustomer(string $publicId): Customer
    {
        try {
            $customer = $this->em->getRepository(Customer::class)->findOneBy([
                'publicId' => Ulid::fromString($publicId),
            ]);
        } catch (\Throwable) {
            throw $this->createNotFoundException('Cliente no encontrado.');
        }

        if (!$customer instanceof Customer) {
            throw $this->createNotFoundException('Cliente no encontrado.');
        }

        return $customer;
    }

    private function findLocation(string $publicId, Customer $customer): CustomerLocation
    {
        try {
            $location = $this->em->getRepository(CustomerLocation::class)->findOneBy([
                'publicId' => Ulid::fromString($publicId),
                'customer' => $customer,
            ]);
        } catch (\Throwable) {
            throw $this->createNotFoundException('Ubicacion no encontrada.');
        }

        if (!$location instanceof CustomerLocation) {
            throw $this->createNotFoundException('Ubicacion no encontrada.');
        }

        return $location;
    }

    private function clearDefaultForCustomer(Customer $customer): void
    {
        $this->em->createQueryBuilder()
            ->update(CustomerLocation::class, 'l')
            ->set('l.isDefault', 'false')
            ->where('l.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->execute();
    }
}
