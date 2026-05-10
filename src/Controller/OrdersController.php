<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OrdersController extends AbstractController
{
    #[Route('/orders/{id}/process', name: 'order_process')]
    public function process(Order $order, EntityManagerInterface $em): Response
    {
        $order->setStatus('Processing');
        $em->flush();

        return $this->redirectToRoute('orders_index');
    }

    #[Route('/orders/{id}/done', name: 'order_done')]
    public function done(Order $order, EntityManagerInterface $em): Response
    {
        $order->setStatus('Completed');
        $em->flush();

        return $this->redirectToRoute('orders_index');
    }

    #[Route('/orders/{id}/cancel', name: 'order_cancel')]
    public function cancel(Order $order, EntityManagerInterface $em): Response
    {
        $order->setStatus('Cancelled');
        $em->flush();

        return $this->redirectToRoute('orders_index');
    }

    #[Route('/orders', name: 'orders_index')]
    public function index(OrderRepository $orderRepo): Response
    {
        $orders = $orderRepo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('orders/index.html.twig', [
            'orders' => $orders
        ]);
    }
}
