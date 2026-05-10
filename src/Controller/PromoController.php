<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PromoController extends AbstractController
{
    #[Route('/promo', name: 'app_promo')]
    public function index(): Response
    {
        return $this->render('promo/index.html.twig', [
            'controller_name' => 'PromoController',
        ]);
    }
}
