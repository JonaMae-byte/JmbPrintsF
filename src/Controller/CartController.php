<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\StocksRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartController extends AbstractController
{
    #[Route('/cart', name: 'cart_index')]
    public function index(SessionInterface $session, ProductRepository $productRepo): Response
    {
        $cart = $session->get('cart', []);
        $items = [];
        $total = 0;

        foreach ($cart as $productId => $data) {
            $product = $productRepo->find($productId);
            if (!$product) continue;

            $qty = $data['qty'];
            $subtotal = $product->getPrice() * $qty;
            $total += $subtotal;

            $items[] = [
                'product' => $product,
                'qty' => $qty,
                'subtotal' => $subtotal
            ];
        }

        return $this->render('cart/index.html.twig', [
            'items' => $items,
            'total' => $total
        ]);
    }

    #[Route('/cart/add/{id}', name: 'cart_add')]
    public function addToCart(
        int $id,
        SessionInterface $session,
        ProductRepository $productRepository,
        StocksRepository $stocksRepository
    ): Response
    {
        $product = $productRepository->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Product not found.');
        }

        $cart = $session->get('cart', []);
        $availableStock = $this->getAvailableStock($product, $stocksRepository);
        $currentQty = isset($cart[$id]) ? (int) $cart[$id]['qty'] : 0;

        if ($availableStock <= 0) {
            $this->addFlash('error', sprintf('"%s" is out of stock.', $product->getName()));
            return $this->redirectToRoute('cart_index');
        }

        if ($currentQty >= $availableStock) {
            $this->addFlash('error', sprintf('Only %d stock available for "%s".', $availableStock, $product->getName()));
            return $this->redirectToRoute('cart_index');
        }

        if (isset($cart[$id])) {
            $cart[$id]['qty']++;
        } else {
            $cart[$id] = ['qty' => 1];
        }

        $session->set('cart', $cart);
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/remove/{id}', name: 'cart_remove')]
    public function remove($id, SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        unset($cart[$id]);

        $session->set('cart', $cart);
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/add/ajax/{id}', name: 'cart_add_ajax', methods: ['POST'])]
    public function addAjax(Product $product, Request $request, SessionInterface $session, StocksRepository $stocksRepository): JsonResponse
    {
        $cart = $session->get('cart', []);
        $id = $product->getId();
        $availableStock = $this->getAvailableStock($product, $stocksRepository);
        $currentQty = isset($cart[$id]) ? (int) $cart[$id]['qty'] : 0;
        $requestedQty = max(1, $request->request->getInt('qty', 1));

        if ($availableStock <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => sprintf('"%s" is out of stock.', $product->getName()),
            ], Response::HTTP_BAD_REQUEST);
        }

        if (($currentQty + $requestedQty) > $availableStock) {
            $remaining = max(0, $availableStock - $currentQty);
            return new JsonResponse([
                'success' => false,
                'message' => $remaining > 0
                    ? sprintf('Only %d more stock available for "%s".', $remaining, $product->getName())
                    : sprintf('No more stock available for "%s".', $product->getName()),
            ], Response::HTTP_BAD_REQUEST);
        }

        if (isset($cart[$id])) {
            $cart[$id]['qty'] += $requestedQty;
        } else {
            $cart[$id] = ['qty' => $requestedQty];
        }

        $session->set('cart', $cart);

        $count = array_sum(array_column($cart, 'qty'));

        return new JsonResponse([
            'success' => true,
            'name' => $product->getName(),
            'count' => $count
        ]);
    }

    #[Route('/cart/count', name: 'cart_count')]
    public function cartCount(SessionInterface $session): JsonResponse
    {
        $cart = $session->get('cart', []);
        $count = array_sum(array_column($cart, 'qty'));

        return new JsonResponse(['count' => $count]);
    }

    private function getAvailableStock(Product $product, StocksRepository $stocksRepository): int
    {
        $availableFromStocks = $stocksRepository->getAvailableQuantityForProductId((int) $product->getId());
        if ($availableFromStocks !== null) {
            return $availableFromStocks;
        }

        return max(0, (int) ($product->getStock() ?? 0));
    }
}
