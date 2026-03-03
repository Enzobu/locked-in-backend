<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Customer;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/customers')]
class AdminCustomerController extends AbstractController
{
    #[Route('', name: 'app_admin_customers_index', methods: ['GET'])]
    public function index(Request $request, CustomerRepository $customerRepository): Response
    {
        $showDeleted = $request->query->getBoolean('deleted');
        $q = $request->query->get('q');

        return $this->render('admin/customers/index.html.twig', [
            'customers' => $customerRepository->searchByDeletionStatus($showDeleted, is_string($q) ? $q : null),
            'showDeleted' => $showDeleted,
            'q' => $q,
        ]);
    }

    #[Route('/new', name: 'app_admin_customers_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $customer = new Customer();
        $customer->addAddress(new Address());
        $form = $this->createForm(CustomerType::class, $customer, ['is_create' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $customer->getAddresses()->isEmpty()) {
            $form->get('addresses')->addError(new FormError('Au moins une adresse est requise.'));
            $this->addFlash('danger', 'Veuillez saisir au moins une adresse');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $customer->setPassword($passwordHasher->hashPassword($customer, $plainPassword));
            $customer->setRoles(['ROLE_CUSTOMER']);

            $entityManager->persist($customer);
            $entityManager->flush();

            $this->addFlash('success', 'Client cree avec succes.');

            return $this->redirectToRoute('app_admin_customers_index');
        }

        return $this->render('admin/customers/form.html.twig', [
            'form' => $form,
            'title' => 'Nouveau client',
            'submitLabel' => 'Creer',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_customers_edit', methods: ['GET', 'POST'])]
    public function edit(Customer $customer, Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        if ($customer->getAddresses()->isEmpty()) {
            $customer->addAddress(new Address());
        }

        $form = $this->createForm(CustomerType::class, $customer, ['is_create' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $customer->getAddresses()->isEmpty()) {
            $form->get('addresses')->addError(new FormError('Au moins une adresse est requise.'));
            $this->addFlash('danger', 'Veuillez saisir au moins une adresse');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            if ($plainPassword !== '') {
                $customer->setPassword($passwordHasher->hashPassword($customer, $plainPassword));
            }

            $customer->setRoles(['ROLE_CUSTOMER']);
            $entityManager->flush();

            $this->addFlash('success', 'Client mis a jour.');

            return $this->redirectToRoute('app_admin_customers_index');
        }

        return $this->render('admin/customers/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier client',
            'submitLabel' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_customers_delete', methods: ['POST'])]
    public function delete(Customer $customer, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_customer_'.$customer->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_admin_customers_index');
        }

        $customer->softDelete();
        $entityManager->flush();

        $this->addFlash('success', 'Client desactive.');

        return $this->redirectToRoute('app_admin_customers_index');
    }

    #[Route('/{id}/restore', name: 'app_admin_customers_restore', methods: ['POST'])]
    public function restore(Customer $customer, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('restore_customer_'.$customer->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_admin_customers_index', ['deleted' => 1]);
        }

        $customer->restore();
        $entityManager->flush();

        $this->addFlash('success', 'Client restaure.');

        return $this->redirectToRoute('app_admin_customers_index', ['deleted' => 1]);
    }
}
