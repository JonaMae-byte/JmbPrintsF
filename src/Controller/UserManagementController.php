<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * User Management: create and edit accounts (staff, admin, or customers).
 * Restricted to ROLE_ADMIN via attribute and access_control (^/admin/users).
 */
#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    #[Route('/', name: 'user_management_index')]
    public function index(
        UserRepository $userRepository,
        OrderRepository $orderRepo,
        ProductRepository $productRepo
    ): Response {

        // Correct counts
        $totalUsers = $userRepository->count([]);
        $totalAdmins = $userRepository->countByRole('ROLE_ADMIN');
        $totalStaff = $userRepository->countByRole('ROLE_STAFF');

        // Other totals
        $totalOrders = $orderRepo->count([]);
        $totalProducts = $productRepo->count([]);

        return $this->render('user_management/index.html.twig', [
            'users' => $userRepository->findAll(),
            'totalUsers' => $totalUsers,
            'totalAdmins' => $totalAdmins,
            'totalStaff' => $totalStaff,
            'totalOrders' => $totalOrders,
            'totalProducts' => $totalProducts,
        ]);
    }


    #[Route('/new', name: 'admin_users_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {

        $user = new User();
        // Default checkbox selection (assignable roles only; ROLE_USER is implicit via getRoles()).
        $user->setRoles(['ROLE_STAFF']);

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedRoles = $user->getAssignableRoles();
            if ([] === $submittedRoles) {
                $submittedRoles = ['ROLE_STAFF'];
            }
            $user->setRoles($submittedRoles);

            // Keep stored emails normalized so staff lookups are consistent.
            $user->setEmail(mb_strtolower(trim((string) $user->getEmail())));

            $hashed = $passwordHasher->hashPassword($user, (string) $user->getPassword());
            $user->setPassword($hashed);

            // Trusted admin-provisioned account: skip customer email verification.
            $user->setIsVerified(true);
            $user->setVerificationToken(null);

            try {
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'User account created successfully.');

                return $this->redirectToRoute('user_management_index');
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'That username or email is already in use.');
            } catch (\Throwable) {
                $this->addFlash('error', 'Could not create the staff account. Please check the fields and try again.');
            }
        }

        return $this->render('user_management/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_users_edit')]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {

        $form = $this->createForm(UserType::class, $user, [
            'password_required' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedRoles = $user->getAssignableRoles();
            if ([] === $submittedRoles) {
                $submittedRoles = ['ROLE_STAFF'];
            }
            $user->setRoles($submittedRoles);
            $user->setEmail(mb_strtolower(trim((string) $user->getEmail())));

            $plainPassword = $form->get('password')->getData();
            if (\is_string($plainPassword) && '' !== trim($plainPassword)) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $em->flush();
            $this->addFlash('success', 'User account updated successfully.');

            return $this->redirectToRoute('user_management_index');
        }

        return $this->render('user_management/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_users_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->get('_token'))) {
            $em->remove($user);
            $em->flush();
        }

        return $this->redirectToRoute('user_management_index');
    }
}
