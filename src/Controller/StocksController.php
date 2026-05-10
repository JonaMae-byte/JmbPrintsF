<?php

namespace App\Controller;

use App\Entity\Stocks;
use App\Entity\ActivityLog;
use App\Form\StocksType;
use App\Repository\StocksRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stocks')]
class StocksController extends AbstractController
{
    #[Route('/', name: 'app_stocks_index')]
    public function index(StocksRepository $stocksRepository): Response
    {
        return $this->render('stocks/index.html.twig', [
            'stocks' => $stocksRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_stocks_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, StocksRepository $stocksRepository): Response
    {
        $stock = new Stocks();
        $form = $this->createForm(StocksType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $quantityToAdd = max(0, (int) ($stock->getQuantity() ?? 0));
            $existingStock = $stocksRepository->findOneBy(['product' => $stock->getProduct()]);

            if ($existingStock) {
                $updatedQuantity = max(0, (int) ($existingStock->getQuantity() ?? 0)) + $quantityToAdd;
                $existingStock->setQuantity($updatedQuantity);
                $existingStock->setUpdatedAt(new \DateTimeImmutable());

                if ($stock->getCategory()) {
                    $existingStock->setCategory($stock->getCategory());
                }

                $entityManager->persist($existingStock);
                $targetStock = $existingStock;
            } else {
                $stock->setCreatedAt(new \DateTimeImmutable());
                $stock->setUpdatedAt(new \DateTimeImmutable());
                $stock->setQuantity($quantityToAdd);
                $entityManager->persist($stock);
                $targetStock = $stock;
            }

            $entityManager->flush(); // generate ID if new stock

            // Keep Product.stock aligned with total Stocks quantity.
            if ($targetStock->getProduct() && $targetStock->getProduct()->getId() !== null) {
                $totalAvailable = $stocksRepository->getAvailableQuantityForProductId((int) $targetStock->getProduct()->getId()) ?? 0;
                $targetStock->getProduct()->setStock(max(0, (int) $totalAvailable));
                $entityManager->persist($targetStock->getProduct());
                $entityManager->flush();
            }

            // -------- Activity Log --------
            $log = new ActivityLog();
            $log->setAction("Add Stock");
            $log->setDetails(
                "Added " . $quantityToAdd . " stocks for product: " . $targetStock->getProduct()->getName() .
                " (Stock ID: " . $targetStock->getId() . ", New Qty: " . $targetStock->getQuantity() . ")"
            );
            $log->setUser(
                $this->getUser()?->getUserIdentifier() ?? "System"
            );

            $log->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($log);
            $entityManager->flush();
            // --------------------------------

            return $this->redirectToRoute('app_stocks_index');
        }

        return $this->render('stocks/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_stocks_show', methods: ['GET'])]
    public function show(Stocks $stock): Response
    {
        return $this->render('stocks/show.html.twig', [
            'stock' => $stock,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_stocks_edit')]
    public function edit(Request $request, Stocks $stock, EntityManagerInterface $entityManager, StocksRepository $stocksRepository): Response
    {
        $currentQuantity = max(0, (int) ($stock->getQuantity() ?? 0));
        $form = $this->createForm(StocksType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $quantityToAdd = max(0, (int) ($stock->getQuantity() ?? 0));
            $stock->setQuantity($currentQuantity + $quantityToAdd);

            $stock->setUpdatedAt(new \DateTimeImmutable());

            // -------- Activity Log --------
            $log = new ActivityLog();
            $log->setAction("Add Stock");
            $log->setDetails(
                "Added " . $quantityToAdd . " stocks for product: " . $stock->getProduct()->getName() .
                " (Stock ID: " . $stock->getId() . ", New Qty: " . $stock->getQuantity() . ")"
            );
            $log->setUser(
                $this->getUser()?->getUserIdentifier() ?? "System"
            );

            $log->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($log);

            // Save both stock update + log
            $entityManager->flush();

            // Keep Product.stock aligned with total Stocks quantity.
            if ($stock->getProduct() && $stock->getProduct()->getId() !== null) {
                $totalAvailable = $stocksRepository->getAvailableQuantityForProductId((int) $stock->getProduct()->getId()) ?? 0;
                $stock->getProduct()->setStock(max(0, (int) $totalAvailable));
                $entityManager->persist($stock->getProduct());
                $entityManager->flush();
            }
            // --------------------------------

            return $this->redirectToRoute('app_stocks_index');
        }

        return $this->render('stocks/edit.html.twig', [
            'form' => $form->createView(),
            'stock' => $stock,
        ]);
    }

    #[Route('/{id}', name: 'app_stocks_delete', methods: ['POST'])]
    public function delete(Request $request, Stocks $stock, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$stock->getId(), $request->get('_token'))) {

            // -------- Activity Log BEFORE delete --------
            $log = new ActivityLog();
            $log->setAction("Delete Stock");
            $log->setDetails(
                "Deleted stock: " . $stock->getProduct()->getName() .
                " (ID: " . $stock->getId() . ")"
            );
            $log->setUser(
                $this->getUser()?->getUserIdentifier() ?? "System"
            );

            $log->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($log);
            // --------------------------------------------

            // Delete stock
            $entityManager->remove($stock);

            // Single flush (log + delete)
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_stocks_index');
    }
}
