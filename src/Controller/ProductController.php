<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ActivityLog;
use App\Form\ProductType;
use App\Repository\ActivityLogRepository;
use App\Repository\ProductRepository;
use App\Repository\StocksRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/product')]
final class ProductController extends AbstractController
{
    #[Route(name: 'app_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        return $this->render('product/index.html.twig', [
            'products' => $productRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->setCreatedBy($this->getUser()?->getUserIdentifier() ?? 'Unknown');
            $product->setCreatedAt(new \DateTimeImmutable());

            // Persist product
            $entityManager->persist($product);
            $entityManager->flush();  // generates product ID

            // LOG ACTIVITY
            $log = new ActivityLog();
            $log->setAction('Create Product');
            $log->setDetails(
                'Product created: ' . $product->getName() .
                ' (ID: ' . $product->getId() . ')'
            );
            $log->setUser($this->getUser()?->getUserIdentifier() ?? 'Unknown');
            $log->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($log);
            $entityManager->flush();

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(
        Product $product,
        StocksRepository $stocksRepository,
        ActivityLogRepository $activityLogRepository
    ): Response
    {
        $quantityWithdrawn = 0;
        foreach ($product->getOrderItems() as $orderItem) {
            $order = $orderItem->getOrder();
            if ($order && strtolower((string) $order->getStatus()) === 'cancelled') {
                continue;
            }
            $quantityWithdrawn += max(0, (int) ($orderItem->getQuantity() ?? 0));
        }

        $stocksLeft = max(0, (int) ($product->getStock() ?? 0));
        $quantityAdded = 0;
        $stock = $stocksRepository->findOneBy(['product' => $product]);
        if ($stock && $stock->getId() !== null) {
            $latestAddLog = $activityLogRepository->createQueryBuilder('a')
                ->andWhere('a.action = :action')
                ->andWhere('a.details LIKE :stockRef')
                ->setParameter('action', 'Add Stock')
                ->setParameter('stockRef', '%Stock ID: ' . $stock->getId() . ',%')
                ->orderBy('a.createdAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($latestAddLog && preg_match('/Added\s+(\d+)\s+stocks/i', (string) $latestAddLog->getDetails(), $matches)) {
                $quantityAdded = (int) $matches[1];
            }
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'quantityAdded' => $quantityAdded,
            'quantityWithdrawn' => $quantityWithdrawn,
            'stocksLeft' => $stocksLeft,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // No persist needed because Product is already managed
            $entityManager->flush();

            // LOG ACTIVITY
            $log = new ActivityLog();
            $log->setAction('Edit Product');
            $log->setDetails(
                'Product updated: ' . $product->getName() .
                ' (ID: ' . $product->getId() . ')'
            );
            $log->setUser($this->getUser()?->getUserIdentifier() ?? 'Unknown');
            $log->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($log);
            $entityManager->flush();

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->getPayload()->getString('_token'))) {

            // LOG BEFORE DELETE
            $log = new ActivityLog();
            $log->setAction('Delete Product');
            $log->setDetails(
                'Deleted product: ' . $product->getName() .
                ' (ID: ' . $product->getId() . ')'
            );
            $log->setUser($this->getUser()?->getEmail() ?? $this->getUser()?->getUsername() ?? 'Unknown');
            $log->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($log);

            // DELETE PRODUCT
            $entityManager->remove($product);

            // single flush (delete + log)
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_product_index');
    }
}
