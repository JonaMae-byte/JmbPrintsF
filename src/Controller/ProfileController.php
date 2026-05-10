<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserProfileEditType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'primary_role_label' => $this->resolvePrimaryRoleLabel($user),
        ]);
    }

    #[Route('/profile/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        if ($this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Staff accounts cannot edit profiles here.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(UserProfileEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $firstPwd = $form->get('plainPassword')->get('first')->getData();
            if (\is_string($firstPwd) && $firstPwd !== '' && \strlen($firstPwd) < 6) {
                $form->get('plainPassword')->get('first')->addError(new FormError('Password must be at least 6 characters.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if (\is_string($plainPassword) && $plainPassword !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $entityManager->flush();

            $this->addFlash('success', 'Your profile has been updated.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    /**
     * Human-readable primary role for display (admin > staff > user).
     */
    private function resolvePrimaryRoleLabel(User $user): string
    {
        $roles = $user->getRoles();

        if (\in_array('ROLE_ADMIN', $roles, true)) {
            return 'Administrator';
        }

        if (\in_array('ROLE_STAFF', $roles, true)) {
            return 'Staff';
        }

        return 'User';
    }
}
