<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Company;
use App\Entity\Locker;
use App\Entity\LockerBay;
use App\Form\CompanyType;
use App\Form\LockerBayType;
use App\Form\LockerType;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/companies')]
class AdminCompanyController extends AbstractController
{
    #[Route('', name: 'app_admin_companies_index', methods: ['GET'])]
    public function index(Request $request, CompanyRepository $companyRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = mb_strtolower(trim((string) $request->query->get('q', '')));
        $companies = $companyRepository->createQueryBuilder('c')
            ->leftJoin('c.address', 'a')->addSelect('a')
            ->leftJoin('c.lockerBays', 'b')->addSelect('b')
            ->leftJoin('b.lockers', 'l')->addSelect('l')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        if ($q !== '') {
            $companies = array_filter($companies, static function (Company $company) use ($q): bool {
                return str_contains(mb_strtolower((string) $company->getName()), $q)
                    || str_contains(mb_strtolower((string) $company->getSiren()), $q)
                    || str_contains(mb_strtolower((string) $company->getSiret()), $q);
            });
        }

        return $this->render('admin/companies/index.html.twig', [
            'companies' => $companies,
            'q' => $request->query->get('q'),
        ]);
    }

    #[Route('/new', name: 'app_admin_companies_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $company = new Company();
        $company->setAddress(new Address());

        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($company);
            $entityManager->flush();

            $this->addFlash('success', 'Société créée.');

            return $this->redirectToRoute('app_admin_companies_show', ['id' => $company->getId()]);
        }

        return $this->render('admin/companies/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle société',
            'submitLabel' => 'Creer',
        ]);
    }

    #[Route('/{id}', name: 'app_admin_companies_show', methods: ['GET'])]
    public function show(Company $company): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $bays = $company->getLockerBays()->toArray();
        usort($bays, static fn (LockerBay $a, LockerBay $b): int => strcmp((string) $a->getName(), (string) $b->getName()));

        return $this->render('admin/companies/show.html.twig', [
            'company' => $company,
            'bays' => $bays,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_companies_edit', methods: ['GET', 'POST'])]
    public function edit(Company $company, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($company->getAddress() === null) {
            $company->setAddress(new Address());
        }

        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Société mise à jour.');

            return $this->redirectToRoute('app_admin_companies_show', ['id' => $company->getId()]);
        }

        return $this->render('admin/companies/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier société',
            'submitLabel' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_companies_delete', methods: ['POST'])]
    public function delete(Company $company, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_company_'.$company->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_admin_companies_index');
        }

        if (!$company->getUsers()->isEmpty()) {
            $this->addFlash('danger', 'Impossible de supprimer : la société possède encore des utilisateurs backoffice.');

            return $this->redirectToRoute('app_admin_companies_show', ['id' => $company->getId()]);
        }

        if (!$company->getLockerBays()->isEmpty()) {
            $this->addFlash('danger', 'Impossible de supprimer : supprimez d\'abord les baies de casiers.');

            return $this->redirectToRoute('app_admin_companies_show', ['id' => $company->getId()]);
        }

        $entityManager->remove($company);
        $entityManager->flush();

        $this->addFlash('success', 'Société supprimée.');

        return $this->redirectToRoute('app_admin_companies_index');
    }

    #[Route('/{id}/locker-bays/new', name: 'app_admin_companies_locker_bays_new', methods: ['GET', 'POST'])]
    public function newLockerBay(Company $company, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $lockerBay = new LockerBay();
        $lockerBay->setCompany($company);

        $form = $this->createForm(LockerBayType::class, $lockerBay);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($lockerBay);
            $entityManager->flush();

            $this->addFlash('success', 'Baie créée.');

            return $this->redirectToRoute('app_admin_companies_show', ['id' => $company->getId()]);
        }

        return $this->render('admin/companies/locker_bay_form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle baie',
            'company' => $company,
            'submitLabel' => 'Creer',
        ]);
    }

    #[Route('/locker-bays/{id}/edit', name: 'app_admin_companies_locker_bays_edit', methods: ['GET', 'POST'])]
    public function editLockerBay(LockerBay $lockerBay, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(LockerBayType::class, $lockerBay);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Baie mise à jour.');

            return $this->redirectToRoute('app_admin_companies_show', ['id' => $lockerBay->getCompany()?->getId()]);
        }

        return $this->render('admin/companies/locker_bay_form.html.twig', [
            'form' => $form,
            'title' => 'Modifier baie',
            'company' => $lockerBay->getCompany(),
            'submitLabel' => 'Enregistrer',
        ]);
    }

    #[Route('/locker-bays/{id}/delete', name: 'app_admin_companies_locker_bays_delete', methods: ['POST'])]
    public function deleteLockerBay(LockerBay $lockerBay, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_bay_'.$lockerBay->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_admin_companies_show', ['id' => $lockerBay->getCompany()?->getId()]);
        }

        if (!$lockerBay->getLockers()->isEmpty()) {
            $this->addFlash('danger', 'Impossible de supprimer la baie : retirez d\'abord ses casiers.');

            return $this->redirectToRoute('app_admin_companies_show', ['id' => $lockerBay->getCompany()?->getId()]);
        }

        $companyId = $lockerBay->getCompany()?->getId();
        $entityManager->remove($lockerBay);
        $entityManager->flush();

        $this->addFlash('success', 'Baie supprimée.');

        return $this->redirectToRoute('app_admin_companies_show', ['id' => $companyId]);
    }

    #[Route('/locker-bays/{id}/lockers/new', name: 'app_admin_companies_lockers_new', methods: ['GET', 'POST'])]
    public function newLocker(LockerBay $lockerBay, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $locker = new Locker();
        $locker->setLockerBay($lockerBay);

        $form = $this->createForm(LockerType::class, $locker);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($locker->getSpecification() === null) {
                $form->addError(new FormError('La specification est obligatoire.'));
            } else {
                $entityManager->persist($locker);
                $entityManager->flush();

                $this->addFlash('success', 'Casier créé.');

                return $this->redirectToRoute('app_admin_locker_bays_show', ['id' => $lockerBay->getId()]);
            }
        }

        return $this->render('admin/companies/locker_form.html.twig', [
            'form' => $form,
            'title' => 'Nouveau casier',
            'lockerBay' => $lockerBay,
            'submitLabel' => 'Creer',
        ]);
    }

    #[Route('/lockers/{id}/edit', name: 'app_admin_companies_lockers_edit', methods: ['GET', 'POST'])]
    public function editLocker(Locker $locker, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(LockerType::class, $locker);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Casier mis à jour.');

            return $this->redirectToRoute('app_admin_locker_bays_show', ['id' => $locker->getLockerBay()?->getId()]);
        }

        return $this->render('admin/companies/locker_form.html.twig', [
            'form' => $form,
            'title' => 'Modifier casier',
            'lockerBay' => $locker->getLockerBay(),
            'submitLabel' => 'Enregistrer',
        ]);
    }

    #[Route('/lockers/{id}/delete', name: 'app_admin_companies_lockers_delete', methods: ['POST'])]
    public function deleteLocker(Locker $locker, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_locker_'.$locker->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_admin_locker_bays_show', ['id' => $locker->getLockerBay()?->getId()]);
        }

        if (!$locker->getReservations()->isEmpty()) {
            $this->addFlash('danger', 'Impossible de supprimer ce casier : des réservations existent.');

            return $this->redirectToRoute('app_admin_locker_bays_show', ['id' => $locker->getLockerBay()?->getId()]);
        }

        $bayId = $locker->getLockerBay()?->getId();
        $entityManager->remove($locker);
        $entityManager->flush();

        $this->addFlash('success', 'Casier supprimé.');

        return $this->redirectToRoute('app_admin_locker_bays_show', ['id' => $bayId]);
    }
}
