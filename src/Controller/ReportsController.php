<?php

namespace App\Controller;

use App\Repository\TransactionReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ReportsController extends AbstractController
{
    #[Route('/reports', name: 'app_reports')]
    public function index(TransactionReportRepository $transactionReportRepository): Response
    {
        // Fetch all reports ordered by transactionDate descending
        $reports = $transactionReportRepository->findBy([], ['transactionDate' => 'DESC']);

        return $this->render('reports/index.html.twig', [
            'reports' => $reports,
        ]);
    }
}
