<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/my-orders')]
class MyOrdersController extends AbstractController
{
    #[Route('/', name: 'my_orders')]
    public function index(OrderRepository $orderRepo): Response
    {
        // REQUIRE LOGIN
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Fetch orders belonging to logged-in user
        $orders = $orderRepo->findBy(
            ['user' => $user], // <-- Correct field
            ['id' => 'DESC']
        );

        return $this->render('my_orders/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/cancel/{id}', name: 'my_orders_cancel')]
    public function cancel(
        int $id,
        OrderRepository $orderRepo,
        EntityManagerInterface $em
    ): Response {

        $order = $orderRepo->find($id);

        if (!$order) {
            $this->addFlash('error', 'Order not found.');
            return $this->redirectToRoute('my_orders');
        }

        // Ensure this order belongs to logged-in user
        if ($order->getUser() !== $this->getUser()) { // <-- Correct field
            $this->addFlash('error', 'You cannot cancel this order.');
            return $this->redirectToRoute('my_orders');
        }

        // Only allow cancel if still pending
        if ($order->getStatus() !== 'Pending') {
            $this->addFlash('error', 'You cannot cancel this order anymore.');
            return $this->redirectToRoute('my_orders');
        }

        // CANCEL THE ORDER
        $order->setStatus('Cancelled');
        $em->flush();

        $this->addFlash('success', 'Your order has been cancelled.');
        return $this->redirectToRoute('my_orders');
    }

    #[Route('/view/{id}', name: 'my_orders_view')]
    public function view(
        int $id,
        OrderRepository $orderRepo
    ): Response {

        $order = $orderRepo->find($id);

        if (!$order) {
            $this->addFlash('error', 'Order not found.');
            return $this->redirectToRoute('my_orders');
        }

        // Ensure user owns this order
        if ($order->getUser() !== $this->getUser()) { // <-- Correct field
            $this->addFlash('error', 'You cannot view this order.');
            return $this->redirectToRoute('my_orders');
        }

        return $this->render('my_orders/view.html.twig', [
            'order' => $order,
            'items' => $order->getOrderItems(),
        ]);
    }
}
