<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\ProductRepository;
use App\Repository\StocksRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class ShopController extends AbstractController
{
    #[Route('/shop', name: 'shop_index')]
    public function index(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findAll();

        return $this->render('shop/index.html.twig', [
            'products' => $products,
        ]);
    }

    

    // Add product -> session cart (GET request is fine for this button)
    #[Route('/cart/add/{id}', name: 'cart_add')]
        public function addToCart(
            int $id,
            ProductRepository $productRepo,
            StocksRepository $stocksRepository,
            SessionInterface $session,
            Request $request
        ): Response
        {
            $product = $productRepo->find($id);
            if (!$product) {
                return $this->json(['status' => 'error', 'message' => 'Product not found']);
            }

            $cart = $session->get('cart', []);
            $available = $this->getAvailableStock($id, $productRepo, $stocksRepository);
            $currentQty = isset($cart[$id]) ? (int) $cart[$id]['qty'] : 0;

            if ($available <= 0) {
                $message = $product->getName() . ' is out of stock.';
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['status' => 'error', 'message' => $message], Response::HTTP_BAD_REQUEST);
                }
                $this->addFlash('error', $message);
                return $this->redirectToRoute('cart_index');
            }

            if ($currentQty >= $available) {
                $message = sprintf('Only %d stock available for %s.', $available, $product->getName());
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['status' => 'error', 'message' => $message], Response::HTTP_BAD_REQUEST);
                }
                $this->addFlash('error', $message);
                return $this->redirectToRoute('cart_index');
            }

            if (isset($cart[$id])) {
                $cart[$id]['qty']++;
            } else {
                $cart[$id] = ['qty' => 1];
            }

            $session->set('cart', $cart);

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'status' => 'success',
                    'message' => $product->getName() . ' added to cart.'
                ]);
            }

            return $this->redirectToRoute('shop_index');
        }


    // View cart
    #[Route('/cart', name: 'cart_index')]
    public function cartIndex(SessionInterface $session, ProductRepository $productRepo): Response
    {
        $cart = $session->get('cart', []);
        $items = [];
        $total = 0;

        foreach ($cart as $productId => $data) {
            $product = $productRepo->find($productId);
            if (!$product) {
                continue;
            }
            $qty = $data['qty'];
            $lineTotal = $product->getPrice() * $qty;
            $items[] = [
                'product' => $product,
                'quantity' => $qty,
                'lineTotal' => $lineTotal,
            ];
            $total += $lineTotal;
        }

        return $this->render('cart/index.html.twig', [
        'items' => $items,
        'total' => $total,
        ]);

    }

    // Remove product from cart
    #[Route('/cart/remove/{id}', name: 'cart_remove')]
    public function removeFromCart($id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        if (isset($cart[$id])) {
            unset($cart[$id]);
            $session->set('cart', $cart);
            $this->addFlash('info', 'Item removed.');
        }

        return $this->redirectToRoute('cart_index');
    }

    // Update cart quantities (POST)
    #[Route('/cart/update', name: 'cart_update', methods: ['POST'])]
public function updateCart(
    Request $request,
    SessionInterface $session,
    ProductRepository $productRepo,
    StocksRepository $stocksRepository
): Response
{
    $quantities = $request->request->all('qty');
    $cart = $session->get('cart', []);
    $adjusted = false;

    foreach ($quantities as $productId => $qty) {
        $qty = max(0, (int) $qty);
        $productId = (int) $productId;

        if ($qty === 0) {
            unset($cart[$productId]);
        } else {
            $available = $this->getAvailableStock($productId, $productRepo, $stocksRepository);
            if ($available <= 0) {
                unset($cart[$productId]);
                $adjusted = true;
                continue;
            }

            if ($qty > $available) {
                $qty = $available;
                $adjusted = true;
            }

            $cart[$productId]['qty'] = $qty;
        }
        }

        $session->set('cart', $cart);
        if ($adjusted) {
            $this->addFlash('info', 'Some quantities were adjusted based on available stock.');
        } else {
            $this->addFlash('success', 'Cart updated.');
        }

        return $this->redirectToRoute('cart_index');
    }


    // Checkout -> create Order + OrderItems
    #[Route('/cart/checkout', name: 'cart_checkout', methods: ['POST'])]
    public function checkout(Request $request, SessionInterface $session, ProductRepository $productRepo, EntityManagerInterface $em): Response
    {
        // require login
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('warning', 'Please login first.');
            return $this->redirectToRoute('app_login'); // adjust login route name if different
        }

        $cart = $session->get('cart', []);
        if (empty($cart)) {
            $this->addFlash('warning', 'Cart is empty.');
            return $this->redirectToRoute('shop_index');
        }

        // create Order
        $order = new Order();
        $order->setCustomerName($user->getUsername()); // or getFullName if you have it
        $order->setStatus('pending');
        $order->setCreatedAt(new \DateTimeImmutable());
        $order->setUser($user);

        $em->persist($order);

        $total = 0;

        foreach ($cart as $productId => $data) {
            $product = $productRepo->find($productId);
            if (!$product) {
                continue;
            }

            $qty = (int) $data['qty'];
            $price = $product->getPrice();
            $subtotal = $price * $qty;

            $orderItem = new OrderItem();
            $orderItem->setOrder($order);
            $orderItem->setProduct($product);
            $orderItem->setQuantity($qty);
            $orderItem->setPrice($price);
            $orderItem->setSubtotal($subtotal);

            $em->persist($orderItem);

            $total += $subtotal;
        }

        // optional: set a total field on Order if you have it (not required)
        // $order->setTotalAmount($total);

        $em->flush();

        // clear cart
        $session->remove('cart');

        $this->addFlash('success', 'Order placed successfully! Order ID: ' . $order->getId());

        return $this->redirectToRoute('app_ordering_show', ['id' => $order->getId()]);
    }

    private function getAvailableStock(
        int $productId,
        ProductRepository $productRepo,
        StocksRepository $stocksRepository
    ): int {
        $fromStocks = $stocksRepository->getAvailableQuantityForProductId($productId);
        if ($fromStocks !== null) {
            return max(0, $fromStocks);
        }

        $product = $productRepo->find($productId);
        if (!$product) {
            return 0;
        }

        return max(0, (int) ($product->getStock() ?? 0));
    }
}
