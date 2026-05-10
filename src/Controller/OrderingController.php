<?php

namespace App\Controller;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Stocks;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\Order;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\StocksRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ordering')]
class OrderingController extends AbstractController
{

    
    #[Route('/', name: 'app_ordering', methods: ['GET'])]
    public function index(
        OrderRepository $orderRepository, 
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        StocksRepository $stocksRepository
    ): Response {
        $products = $productRepository->findAll();

        $stocksByProductId = [];
        foreach ($stocksRepository->findAll() as $stock) {
            $product = $stock->getProduct();
            if (!$product || $product->getId() === null) {
                continue;
            }

            $productId = $product->getId();
            $stocksByProductId[$productId] = ($stocksByProductId[$productId] ?? 0) + max(0, (int) $stock->getQuantity());
        }

        // Fallback for products that don't have a Stocks record yet.
        foreach ($products as $product) {
            if ($product->getId() === null) {
                continue;
            }

            if (!isset($stocksByProductId[$product->getId()])) {
                $stocksByProductId[$product->getId()] = max(0, (int) ($product->getStock() ?? 0));
            }
        }

        return $this->render('ordering/index.html.twig', [
            'orders' => $orderRepository->findAll(),
            'products' => $products,
            'categories' => $categoryRepository->findAll(), // ⭐ Categories for filter dropdown
            'stocksByProductId' => $stocksByProductId,
        ]);
    }

    #[Route('/new', name: 'app_ordering_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $order = new Order();
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($order);
            $em->flush();

            return $this->redirectToRoute('app_ordering');
        }

        return $this->render('ordering/new.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/show/{id}', name: 'app_ordering_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('ordering/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/edit/{id}', name: 'app_ordering_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Order $order, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('app_ordering');
        }

        return $this->render('ordering/edit.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/delete/{id}', name: 'app_ordering_delete', methods: ['POST'])]
    public function delete(Request $request, Order $order, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->get('_token'))) {
            $em->remove($order);
            $em->flush();
        }

        return $this->redirectToRoute('app_ordering');
    }

    #[Route('/checkout', name: 'app_ordering_checkout')]
    public function checkout(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $em,
        ProductRepository $productRepo,
        StocksRepository $stocksRepository
    ): Response {
        $cart = $session->get('cart', []);

        if (empty($cart)) {
            $this->addFlash('error', 'Your cart is empty.');
            return $this->redirectToRoute('cart_index');
        }

        $cartItems = [];
        $validatedCart = [];
        $hasStockIssue = false;

        foreach ($cart as $productId => $data) {
            $product = $productRepo->find($productId);
            if (!$product) {
                continue;
            }

            $qty = max(1, (int) ($data['qty'] ?? 1));
            $available = $this->getAvailableStockQuantity($product, $stocksRepository);

            if ($available < $qty) {
                $hasStockIssue = true;
                $this->addFlash(
                    'error',
                    $available <= 0
                        ? sprintf('"%s" is out of stock.', $product->getName())
                        : sprintf('Only %d stock available for "%s".', $available, $product->getName())
                );

                if ($available <= 0) {
                    unset($cart[$productId]);
                    continue;
                }

                $qty = $available;
                $cart[$productId]['qty'] = $qty;
            }

            $cartItems[$productId] = [
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'qty' => $qty,
            ];
            $validatedCart[$productId] = ['product' => $product, 'qty' => $qty];
        }

        if (empty($validatedCart)) {
            $session->set('cart', []);
            $this->addFlash('error', 'Your cart has no valid items for checkout.');
            return $this->redirectToRoute('cart_index');
        }

        if ($hasStockIssue) {
            $session->set('cart', $cart);
        }

        if ($request->isMethod('POST')) {
            $order = new Order();
            $order->setCustomerName($request->request->get('customer_name'));
            $order->setStatus("Pending");
            $order->setCreatedAt(new \DateTimeImmutable());

            if ($this->getUser()) {
                $order->setUser($this->getUser());
            }

            $conn = $em->getConnection();
            $conn->beginTransaction();
            try {
                $em->persist($order);

                foreach ($validatedCart as $entry) {
                    /** @var Product $product */
                    $product = $entry['product'];
                    $qty = (int) $entry['qty'];

                    $available = $this->getAvailableStockQuantity($product, $stocksRepository);
                    if ($available < $qty) {
                        throw new \RuntimeException(
                            $available <= 0
                                ? sprintf('"%s" is out of stock.', $product->getName())
                                : sprintf('Only %d stock available for "%s".', $available, $product->getName())
                        );
                    }

                    $item = new OrderItem();
                    $item->setOrder($order);
                    $item->setProduct($product);
                    $item->setQuantity($qty);
                    $item->setPrice($product->getPrice());
                    $item->setSubtotal($product->getPrice() * $qty);
                    $em->persist($item);

                    $this->deductStockQuantity($product, $qty, $stocksRepository, $em);
                }

                $em->flush();
                $conn->commit();
            } catch (\Throwable $e) {
                $conn->rollBack();
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('app_ordering_checkout');
            }

            $session->remove('cart');
            $this->addFlash('success', 'Your order has been placed successfully!');
            return $this->redirectToRoute('my_orders');
        }

        return $this->render('ordering/checkout.html.twig', [
            'cart' => $cartItems,
        ]);
    }

    private function getAvailableStockQuantity(Product $product, StocksRepository $stocksRepository): int
    {
        $fromStocks = $stocksRepository->getAvailableQuantityForProductId((int) $product->getId());
        if ($fromStocks !== null) {
            return max(0, $fromStocks);
        }

        return max(0, (int) ($product->getStock() ?? 0));
    }

    private function deductStockQuantity(
        Product $product,
        int $qtyToDeduct,
        StocksRepository $stocksRepository,
        EntityManagerInterface $em
    ): void {
        $stocks = $stocksRepository->findBy(['product' => $product], ['id' => 'ASC']);
        $remaining = $qtyToDeduct;

        /** @var Stocks $stock */
        foreach ($stocks as $stock) {
            if ($remaining <= 0) {
                break;
            }

            $current = max(0, (int) $stock->getQuantity());
            if ($current === 0) {
                continue;
            }

            $deduct = min($current, $remaining);
            $stock->setQuantity($current - $deduct);
            $stock->setUpdatedAt(new \DateTimeImmutable());
            $remaining -= $deduct;
        }

        if ($remaining > 0) {
            $fallback = max(0, (int) ($product->getStock() ?? 0));
            if ($fallback < $remaining) {
                throw new \RuntimeException(sprintf('Stock mismatch for "%s". Please review cart quantities.', $product->getName()));
            }

            $product->setStock($fallback - $remaining);
        } else {
            // Keep Product.stock in sync when stocks table is used as source.
            $latest = $stocksRepository->getAvailableQuantityForProductId((int) $product->getId());
            $product->setStock(max(0, (int) ($latest ?? 0)));
        }

        $em->persist($product);
    }

}
