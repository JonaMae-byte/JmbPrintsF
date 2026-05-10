<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LandingController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/landing', name: 'app_landing')]
    public function index(ProductRepository $productRepository, ParameterBagInterface $params): Response
    {
        if ($this->getUser() && $this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_staff_dashboard');
        }

        $embedUrl = trim((string) $params->get('contact_google_form_embed_url'));
        $openUrl = '';
        if ($embedUrl !== '') {
            $openUrl = str_replace(['?embedded=true', '&embedded=true'], ['', ''], $embedUrl);
            $openUrl = rtrim($openUrl, '?&');
        }

        return $this->render('landing/index.html.twig', [
            'controller_name' => 'LandingController',
            'popular_products' => $productRepository->findTopSellingWithQuantities(6),
            'contact_form_embed_url' => $embedUrl,
            'contact_form_open_url' => $openUrl,
        ]);
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(ParameterBagInterface $params): Response
    {
        $embedUrl = trim((string) $params->get('contact_google_form_embed_url'));
        $openUrl = '';
        if ($embedUrl !== '') {
            $openUrl = str_replace(['?embedded=true', '&embedded=true'], ['', ''], $embedUrl);
            $openUrl = rtrim($openUrl, '?&');
        }

        return $this->render('landing/contact.html.twig', [
            'contact_form_embed_url' => $embedUrl,
            'contact_form_open_url' => $openUrl,
        ]);
    }

    #[Route('/landing/why-us', name: 'app_landing_why_us')]
    public function whyUs(): Response
    {
        return $this->render('landing/why_us.html.twig');
    }

    #[Route('/landing/about', name: 'app_landing_about')]
    public function about(): Response
    {
        return $this->render('landing/about.html.twig');
    }

    #[Route('/landing/products', name: 'app_landing_products')]
    public function products(ProductRepository $productRepository): Response
    {
        return $this->render('landing/products.html.twig', [
            'popular_products' => $productRepository->findTopSellingWithQuantities(6),
        ]);
    }

    #[Route('/landing/team', name: 'app_landing_team')]
    public function team(): Response
    {
        return $this->render('landing/team.html.twig');
    }

}
