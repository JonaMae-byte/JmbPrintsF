<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_settings')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(SessionInterface $session): Response
    {
        $settings = $session->get('app_settings', []);
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('settings/index.html.twig', [
            'settings' => $settings,
            'admin' => $user,
        ]);
    }

    #[Route('/settings/update', name: 'app_settings_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(
        Request $request,
        SessionInterface $session,
    ): Response {
        $settings = [
            'name' => $this->emptyToNull($request->request->getString('name')),
            'email' => $this->emptyToNull($request->request->getString('email')),
            'phone' => $this->emptyToNull($request->request->getString('phone')),
            'website' => $this->emptyToNull($request->request->getString('website')),
            'address' => $this->emptyToNull($request->request->getString('address')),
            'facebook' => $this->emptyToNull($request->request->getString('facebook')),
            'instagram' => $this->emptyToNull($request->request->getString('instagram')),
            'linkedin' => $this->emptyToNull($request->request->getString('linkedin')),
            'dribbble' => $this->emptyToNull($request->request->getString('dribbble')),
            'language_currency' => $request->request->getString('language_currency') ?: 'en-PHP',
            'theme' => $request->request->getString('theme') ?: 'light',
            'timezone' => $request->request->getString('timezone') ?: 'Asia/Manila',
            'date_format' => $request->request->getString('date_format') ?: 'Y-m-d',
        ];

        $session->set('app_settings', $settings);

        $this->addFlash('success', 'Settings saved successfully.');

        return $this->redirectToRoute('app_settings');
    }

    private function emptyToNull(string $value): ?string
    {
        $t = trim($value);

        return $t === '' ? null : $t;
    }
}
