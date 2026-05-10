<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[Route('/admin/customers')]
class CustomersController extends AbstractController
{
    #[Route('/', name: 'app_customers')]
    public function index(
        UserRepository $userRepo,
        OrderRepository $orderRepo
    ): Response {

        $users = $userRepo->findAll();
        $rows = [];

        foreach ($users as $user) {

            // GET ORDERS
            $orders = $orderRepo->findBy(['user' => $user], ['createdAt' => 'DESC']);

            // HIDE users with 0 orders
            if (count($orders) === 0) {
                continue;
            }

            // HIDE admin if ang admin walay order
            if (in_array('ROLE_ADMIN', $user->getRoles()) && count($orders) === 0) {
                continue;
            }

            // Compute summary
            $totalOrders = count($orders);
            $totalSpent = 0;
            $lastOrderDate = null;

            foreach ($orders as $order) {
                foreach ($order->getOrderItems() as $item) {
                    $subtotal = $item->getSubtotal() ?? ($item->getPrice() * $item->getQuantity());
                    $totalSpent += $subtotal;
                }
            }

            if ($totalOrders > 0) {
                $lastOrderDate = $orders[0]->getCreatedAt();
            }

            // Add row
            $rows[] = [
                'user' => $user,
                'totalOrders' => $totalOrders,
                'totalSpent' => $totalSpent,
                'lastOrderDate' => $lastOrderDate,
            ];
        }

        return $this->render('customers/index.html.twig', [
            'rows' => $rows
        ]);


        return $this->render('customers/index.html.twig', [
            'rows' => $rows
        ]);
    }

    #[Route('/{id}/orders', name: 'customer_orders')]
    public function orders(User $user): Response
    {
        return $this->render('customers/orders.html.twig', [
            'user' => $user,
            'orders' => $user->getOrders(),
        ]);
    }

    

    #[Route('/delete/{id}', name: 'customer_delete', methods: ['POST'])]
    public function delete(
        int $id,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): RedirectResponse {

        $user = $userRepo->find($id);

        if (!$user) {
            $this->addFlash('error', 'Customer not found.');
            return $this->redirectToRoute('app_customers');
        }

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', 'Customer deleted successfully.');
        return $this->redirectToRoute('app_customers');
    }
}
