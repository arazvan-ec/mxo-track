<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\UserRole;
use App\Form\DriverType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/drivers')]
#[IsGranted('ROLE_OPERATOR')]
class DriverAdminController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Route('', name: 'admin_drivers_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = self::ITEMS_PER_PAGE;

        $qb = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_DRIVER%')
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $drivers = $qb->getQuery()->getResult();

        $total = (int) $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_DRIVER%')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($total / $limit));

        return $this->render('admin/driver/index.html.twig', [
            'drivers' => $drivers,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/new', name: 'admin_drivers_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $driver = new User('');
        $form = $this->createForm(DriverType::class, $driver);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            if (empty($plainPassword)) {
                $this->addFlash('error', 'La contrasena es obligatoria al crear un conductor.');

                return $this->render('admin/driver/form.html.twig', [
                    'form' => $form,
                    'driver' => $driver,
                ]);
            }

            $driver->setPassword($this->passwordHasher->hashPassword($driver, $plainPassword));
            $driver->assignRole(UserRole::DRIVER);

            $this->em->persist($driver);
            $this->em->flush();

            $this->addFlash('success', 'Conductor creado correctamente.');

            return $this->redirectToRoute('admin_drivers_index');
        }

        return $this->render('admin/driver/form.html.twig', [
            'form' => $form,
            'driver' => $driver,
        ]);
    }

    #[Route('/{publicId}/edit', name: 'admin_drivers_edit', methods: ['GET', 'POST'])]
    public function edit(string $publicId, Request $request): Response
    {
        $driver = $this->userRepository->findOneByPublicId($publicId);

        if (!$driver instanceof User) {
            throw $this->createNotFoundException('Conductor no encontrado.');
        }

        if (!$driver->hasRole('ROLE_DRIVER')) {
            throw $this->createNotFoundException('Conductor no encontrado.');
        }

        $form = $this->createForm(DriverType::class, $driver);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            if (!empty($plainPassword)) {
                $driver->setPassword($this->passwordHasher->hashPassword($driver, $plainPassword));
            }

            $this->em->flush();

            $this->addFlash('success', 'Conductor actualizado correctamente.');

            return $this->redirectToRoute('admin_drivers_index');
        }

        return $this->render('admin/driver/form.html.twig', [
            'form' => $form,
            'driver' => $driver,
        ]);
    }

    #[Route('/{publicId}/delete', name: 'admin_drivers_delete', methods: ['POST'])]
    public function delete(string $publicId, Request $request): Response
    {
        $driver = $this->userRepository->findOneByPublicId($publicId);

        if (!$driver instanceof User) {
            throw $this->createNotFoundException('Conductor no encontrado.');
        }

        if (!$this->isCsrfTokenValid('delete-driver-' . $publicId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_drivers_index');
        }

        $driver->setActive(false);
        $this->em->flush();

        $this->addFlash('success', 'Conductor desactivado correctamente.');

        return $this->redirectToRoute('admin_drivers_index');
    }
}
