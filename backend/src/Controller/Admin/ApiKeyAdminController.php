<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ApiKey;
use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

#[Route('/admin/api-keys')]
#[IsGranted('ROLE_ADMIN')]
class ApiKeyAdminController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'admin_api_keys_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = self::ITEMS_PER_PAGE;

        $qb = $this->em->createQueryBuilder()
            ->select('a', 'c')
            ->from(ApiKey::class, 'a')
            ->join('a.customer', 'c')
            ->orderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $apiKeys = $qb->getQuery()->getResult();

        $total = (int) $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(ApiKey::class, 'a')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($total / $limit));

        $customers = $this->em->getRepository(Customer::class)->findBy(
            ['isActive' => true],
            ['name' => 'ASC'],
        );

        return $this->render('admin/api_keys/index.html.twig', [
            'apiKeys' => $apiKeys,
            'customers' => $customers,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/create', name: 'admin_api_keys_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('create-api-key', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_api_keys_index');
        }

        $customerPublicId = $request->request->getString('customer_id');
        $name = trim($request->request->getString('name'));
        $rateLimitPerMinute = $request->request->getInt('rate_limit', 60);

        if ($name === '') {
            $this->addFlash('error', 'El nombre de la API key es obligatorio.');

            return $this->redirectToRoute('admin_api_keys_index');
        }

        $customer = $this->findCustomerByPublicId($customerPublicId);
        if (!$customer instanceof Customer) {
            $this->addFlash('error', 'Cliente no encontrado.');

            return $this->redirectToRoute('admin_api_keys_index');
        }

        // Generate a random API key and hash it
        $rawKey = bin2hex(random_bytes(32));
        $keyHash = hash('sha256', $rawKey);

        $apiKey = new ApiKey($customer, $keyHash, $name);
        $apiKey->setRateLimitPerMinute(max(1, min(1000, $rateLimitPerMinute)));

        $this->em->persist($apiKey);
        $this->em->flush();

        // Show the raw key to the admin (only time it's visible)
        $this->addFlash('success', sprintf(
            'API key creada. Clave: %s (guardar ahora, no se puede volver a ver)',
            $rawKey,
        ));

        return $this->redirectToRoute('admin_api_keys_index');
    }

    #[Route('/{publicId}/toggle', name: 'admin_api_keys_toggle', methods: ['POST'])]
    public function toggle(string $publicId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle-api-key-' . $publicId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_api_keys_index');
        }

        $apiKey = $this->findApiKeyByPublicId($publicId);
        if (!$apiKey instanceof ApiKey) {
            $this->addFlash('error', 'API key no encontrada.');

            return $this->redirectToRoute('admin_api_keys_index');
        }

        $apiKey->setActive(!$apiKey->isActive());
        $this->em->flush();

        $this->addFlash('success', sprintf(
            'API key "%s" %s.',
            $apiKey->getName(),
            $apiKey->isActive() ? 'activada' : 'desactivada',
        ));

        return $this->redirectToRoute('admin_api_keys_index');
    }

    #[Route('/{publicId}/delete', name: 'admin_api_keys_delete', methods: ['POST'])]
    public function delete(string $publicId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-api-key-' . $publicId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_api_keys_index');
        }

        $apiKey = $this->findApiKeyByPublicId($publicId);
        if (!$apiKey instanceof ApiKey) {
            $this->addFlash('error', 'API key no encontrada.');

            return $this->redirectToRoute('admin_api_keys_index');
        }

        $this->em->remove($apiKey);
        $this->em->flush();

        $this->addFlash('success', 'API key eliminada correctamente.');

        return $this->redirectToRoute('admin_api_keys_index');
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

    private function findApiKeyByPublicId(string $publicId): ?ApiKey
    {
        try {
            return $this->em->getRepository(ApiKey::class)->findOneBy([
                'publicId' => Ulid::fromString($publicId),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}
