<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TransactionReportController extends AbstractController
{
    #[Route('/transaction/report', name: 'app_transaction_report')]
    public function index(OrderRepository $orderRepository): Response
    {
        // Only admin
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->render('activity_log/limited_access.html.twig');
        }

        // Fetch all orders (newest first)
        $orders = $orderRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('transaction_report/index.html.twig', [
            'orders' => $orders  // <<< IMPORTANT: this must be "orders"
        ]);
    }
}
