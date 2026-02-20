<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\CustomerUserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/users')]
class UserAdminController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Route('', name: 'admin_users_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = self::ITEMS_PER_PAGE;

        $qb = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $users = $qb->getQuery()->getResult();

        $total = (int) $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($total / $limit));

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $editedUser = new User('');
        $form = $this->createForm(CustomerUserType::class, $editedUser);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            if (empty($plainPassword)) {
                $this->addFlash('error', 'La contrasena es obligatoria al crear un usuario.');

                return $this->render('admin/user/form.html.twig', [
                    'form' => $form,
                    'editedUser' => $editedUser,
                ]);
            }

            $editedUser->setPassword($this->passwordHasher->hashPassword($editedUser, $plainPassword));

            $role = $form->get('role')->getData();
            $editedUser->setRoles([$role]);

            $this->em->persist($editedUser);
            $this->em->flush();

            $this->addFlash('success', 'Usuario creado correctamente.');

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'editedUser' => $editedUser,
        ]);
    }

    #[Route('/{publicId}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(string $publicId, Request $request): Response
    {
        $editedUser = $this->userRepository->findOneByPublicId($publicId);

        if (!$editedUser instanceof User) {
            throw $this->createNotFoundException('Usuario no encontrado.');
        }

        $form = $this->createForm(CustomerUserType::class, $editedUser);

        // Pre-fill the unmapped role field with the user's primary role
        $currentRoles = $editedUser->getRoles();
        $primaryRole = $this->determinePrimaryRole($currentRoles);
        $form->get('role')->setData($primaryRole);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            if (!empty($plainPassword)) {
                $editedUser->setPassword($this->passwordHasher->hashPassword($editedUser, $plainPassword));
            }

            $role = $form->get('role')->getData();
            $editedUser->setRoles([$role]);

            $this->em->flush();

            $this->addFlash('success', 'Usuario actualizado correctamente.');

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'editedUser' => $editedUser,
        ]);
    }

    #[Route('/{publicId}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function delete(string $publicId, Request $request): Response
    {
        $editedUser = $this->userRepository->findOneByPublicId($publicId);

        if (!$editedUser instanceof User) {
            throw $this->createNotFoundException('Usuario no encontrado.');
        }

        if (!$this->isCsrfTokenValid('delete-user-' . $publicId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_users_index');
        }

        $editedUser->setActive(false);
        $this->em->flush();

        $this->addFlash('success', 'Usuario desactivado correctamente.');

        return $this->redirectToRoute('admin_users_index');
    }

    private function determinePrimaryRole(array $roles): string
    {
        $priority = ['ROLE_ADMIN', 'ROLE_OPERATOR', 'ROLE_CUSTOMER', 'ROLE_DRIVER'];

        foreach ($priority as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return 'ROLE_CUSTOMER';
    }
}
