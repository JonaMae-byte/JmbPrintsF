<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff')]
#[IsGranted('ROLE_STAFF')]
final class StaffDashboardController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
        private readonly ManagerRegistry $doctrine,
    ) {
    }

    #[Route('/dashboard', name: 'app_staff_dashboard')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('The staff dashboard is only for staff accounts.');
        }

        $em = $this->doctrine->getManager();

        $totalOrders = (int) $this->orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $customersCount = $this->userRepository->countDistinctUsersWithOrders();

        $revenueResult = $em->createQuery('SELECT SUM(oi.subtotal) FROM App\Entity\OrderItem oi')
            ->getSingleScalarResult();
        $totalSales = $revenueResult ? (float) $revenueResult : 0.0;

        $statusCounts = $this->orderRepository->countGroupedByStatus();
        $recentOrders = $this->orderRepository->findRecentOrdered(12);

        $pendingCount = $statusCounts['Pending'] ?? 0;
        $completedCount = $statusCounts['Completed'] ?? 0;

        $preferredOrder = ['Pending', 'Processing', 'Completed', 'Cancelled'];
        $statusCountsSorted = [];
        foreach ($preferredOrder as $label) {
            if (\array_key_exists($label, $statusCounts)) {
                $statusCountsSorted[$label] = $statusCounts[$label];
            }
        }
        foreach ($statusCounts as $label => $count) {
            if (!\array_key_exists($label, $statusCountsSorted)) {
                $statusCountsSorted[$label] = $count;
            }
        }

        return $this->render('staff/dashboard.html.twig', [
            'total_orders' => $totalOrders,
            'customers_count' => $customersCount,
            'total_sales' => $totalSales,
            'pending_count' => $pendingCount,
            'completed_count' => $completedCount,
            'status_counts' => $statusCountsSorted,
            'recent_orders' => $recentOrders,
        ]);
    }
}
