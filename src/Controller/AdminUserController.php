<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users')]
class AdminUserController extends AbstractController
{
    #[Route('', name: 'app_admin_users_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $showDeleted = $request->query->getBoolean('deleted');
        $q = $request->query->get('q');

        return $this->render('admin/users/index.html.twig', [
            'users' => $userRepository->searchByDeletionStatus($showDeleted, is_string($q) ? $q : null),
            'showDeleted' => $showDeleted,
            'q' => $q,
        ]);
    }

    #[Route('/new', name: 'app_admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_create' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur backoffice créé avec succès.');

            return $this->redirectToRoute('app_admin_users_index');
        }

        return $this->render('admin/users/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvel utilisateur backoffice',
            'submitLabel' => 'Creer',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_create' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            if ($plainPassword !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur backoffice mis à jour.');

            return $this->redirectToRoute('app_admin_users_index');
        }

        return $this->render('admin/users/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier utilisateur backoffice',
            'submitLabel' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_users_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_admin_users_index');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            $this->addFlash('warning', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('app_admin_users_index');
        }

        $user->softDelete();
        $entityManager->flush();

        $this->addFlash('success', 'Utilisateur désactivé.');

        return $this->redirectToRoute('app_admin_users_index');
    }

    #[Route('/{id}/restore', name: 'app_admin_users_restore', methods: ['POST'])]
    public function restore(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('restore_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_admin_users_index', ['deleted' => 1]);
        }

        $user->restore();
        $entityManager->flush();

        $this->addFlash('success', 'Utilisateur restauré.');

        return $this->redirectToRoute('app_admin_users_index', ['deleted' => 1]);
    }
}
