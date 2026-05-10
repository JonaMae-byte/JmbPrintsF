<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\StocksRepository;
use App\Repository\OrderRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private OrderRepository $orderRepository,
        private StocksRepository $stocksRepository,
        private ProductRepository $productRepository,
        private ManagerRegistry $doctrine
    ) {
    }

    // ✅ MAIN DASHBOARD (HTML)
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        $stats = $this->computeStats();

        return $this->render('dashboard/index.html.twig', $stats);
    }


    // ✅ API (JSON)
    #[Route('/dashboard/stats', name: 'app_dashboard_stats')]
    public function stats(): JsonResponse
    {
        $stats = $this->computeStats();

        return $this->json($stats);
    }

    /**
     * Centralized stats computation (used by index and JSON endpoint).
     *
     * Returns an array suitable for Twig / JSON:
     *  - totalOrders (int)
     *  - pendingOrders (int)
     *  - revenue (float)
     *  - lowStockCount (int)
     *  - lowStocks (array: productName, quantity)
     *  - topProducts (array: name, ordersCount, revenue)
     *  - printingSchedule (array of orders with id, customerName, status, createdAt, items)
     */
    private function computeStats(): array
    {
        $em = $this->doctrine->getManager();

        // 1) Total orders
        $totalOrders = (int) $this->orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 2) Pending orders (case-sensitive based on your entity default 'Pending')
        $pendingOrders = (int) $this->orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status = :pending')
            ->setParameter('pending', 'Pending')
            ->getQuery()
            ->getSingleScalarResult();

        // 3) Revenue (sum of order item subtotals)
        // Assumes you have OrderItem entity with field `subtotal`.
        $revenueResult = $em->createQuery('SELECT SUM(oi.subtotal) FROM App\Entity\OrderItem oi')
            ->getSingleScalarResult();
        $revenue = $revenueResult ? (float) $revenueResult : 0.0;

        // 4) Low stock alerts (threshold = 10)
        $threshold = 10;
        $lowStocksQ = $em->createQuery(
            'SELECT s, p FROM App\Entity\Stocks s JOIN s.product p WHERE s.quantity < :threshold ORDER BY s.quantity ASC'
        )->setParameter('threshold', $threshold);

        $lowStocksEntities = $lowStocksQ->getResult();

        $lowStocks = [];
        foreach ($lowStocksEntities as $s) {
            // $s is Stocks entity
            $lowStocks[] = [
                'productName' => (string) $s->getProduct(),
                'quantity' => $s->getQuantity(),
                'stockId' => $s->getId(),
            ];
        }

        $lowStockCount = count($lowStocks);

        // 5) Top products (by number of order items) — limit 5
        // Assumes OrderItem has relationship to Product via 'product'
        $topProductsQ = $em->createQuery(
            'SELECT p.id, p.name AS name, COUNT(oi.id) AS ordersCount, SUM(oi.subtotal) AS revenue
             FROM App\Entity\OrderItem oi
             JOIN oi.product p
             GROUP BY p.id
             ORDER BY ordersCount DESC'
        )->setMaxResults(5);

        $topProductsRaw = $topProductsQ->getResult();

        $topProducts = [];
        foreach ($topProductsRaw as $row) {
            $topProducts[] = [
                'name' => $row['name'],
                'ordersCount' => (int) $row['ordersCount'],
                'revenue' => $row['revenue'] !== null ? (float) $row['revenue'] : 0.0,
            ];
        }

        // 6) Printing schedule — upcoming/active orders (not completed) — limit 10
        // Since you don't have a 'deadline' field, we'll show createdAt and status
        $scheduleQ = $this->orderRepository->createQueryBuilder('o')
            ->andWhere('o.status != :completed')
            ->setParameter('completed', 'Completed')
            ->orderBy('o.createdAt', 'ASC')
            ->setMaxResults(10)
            ->getQuery();

        /** @var \App\Entity\Order[] $scheduleOrders */
        $scheduleOrders = $scheduleQ->getResult();

        $printingSchedule = [];
        foreach ($scheduleOrders as $o) {
            $items = [];
            foreach ($o->getOrderItems() as $oi) {
                $product = $oi->getProduct();
                $items[] = [
                    'productName' => $product ? $product->getName() : 'Unknown',
                    'qty' => $oi->getQuantity(),
                    'price' => $oi->getPrice(),
                    'subtotal' => $oi->getSubtotal(),
                ];
            }

            $printingSchedule[] = [
                'id' => $o->getId(),
                'customerName' => $o->getCustomerName(),
                'status' => $o->getStatus(),
                'createdAt' => $o->getCreatedAt()?->format('Y-m-d H:i'),
                'items' => $items,
            ];
        }

        $notifications = [];

        // 7) Recent orders notifications
        $recentOrders = $this->orderRepository->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        foreach ($recentOrders as $order) {
            $notifications[] = [
                'type' => 'order',
                'severity' => 'info',
                'title' => sprintf('New order #%d', $order->getId()),
                'message' => sprintf(
                    '%s placed an order (%s).',
                    $order->getCustomerName() ?: 'Customer',
                    $order->getStatus()
                ),
                'timestamp' => $order->getCreatedAt()?->format('Y-m-d H:i') ?? 'Just now',
            ];
        }

        // 8) Stock notifications (aggregate per product from Stocks table)
        $aggregatedStockRows = $em->createQuery(
            'SELECT p.id AS productId, p.name AS productName, SUM(s.quantity) AS quantity
             FROM App\Entity\Stocks s
             JOIN s.product p
             GROUP BY p.id, p.name
             ORDER BY quantity ASC'
        )->getResult();

        if (count($aggregatedStockRows) > 0) {
            foreach ($aggregatedStockRows as $row) {
                $qty = (int) ($row['quantity'] ?? 0);
                $productName = (string) ($row['productName'] ?? 'Unknown product');

                if ($qty <= 0) {
                    $notifications[] = [
                        'type' => 'stock_out',
                        'severity' => 'danger',
                        'title' => 'Out of stock',
                        'message' => sprintf('%s is out of stock (0 left).', $productName),
                        'timestamp' => 'Now',
                    ];
                } elseif ($qty < $threshold) {
                    $notifications[] = [
                        'type' => 'stock_low',
                        'severity' => 'warning',
                        'title' => 'Low stock alert',
                        'message' => sprintf('%s is low on stock (%d left).', $productName, $qty),
                        'timestamp' => 'Now',
                    ];
                }
            }
        } else {
            // Fallback: use Product.stock when there are no Stocks records.
            foreach ($this->productRepository->findAll() as $product) {
                $qty = max(0, (int) ($product->getStock() ?? 0));
                if ($qty <= 0) {
                    $notifications[] = [
                        'type' => 'stock_out',
                        'severity' => 'danger',
                        'title' => 'Out of stock',
                        'message' => sprintf('%s is out of stock (0 left).', $product->getName()),
                        'timestamp' => 'Now',
                    ];
                } elseif ($qty < $threshold) {
                    $notifications[] = [
                        'type' => 'stock_low',
                        'severity' => 'warning',
                        'title' => 'Low stock alert',
                        'message' => sprintf('%s is low on stock (%d left).', $product->getName(), $qty),
                        'timestamp' => 'Now',
                    ];
                }
            }
        }

        return [
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'revenue' => $revenue,
            'lowStockCount' => $lowStockCount,
            'lowStocks' => $lowStocks,
            'topProducts' => $topProducts,
            'printingSchedule' => $printingSchedule,
            'lowStockThreshold' => $threshold,
            'notifications' => $notifications,
        ];
    }
}
