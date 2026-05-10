<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ActivityLogController extends AbstractController
{
    #[Route('/activity/log', name: 'app_activity_log')]
    public function index(ActivityLogRepository $repo): Response
    {
        // === ONLY ADMIN CAN ACCESS ===
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->render('activity_log/limited_access.html.twig');
        }

        return $this->render('activity_log/index.html.twig');
    }
    #[Route('/activity/log/data', name: 'app_activity_log_data', methods: ['GET'])]
    public function logData(EntityManagerInterface $em): Response
    {
        // === ONLY ADMIN CAN ACCESS API ===
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->render('activity_log/limited_access.html.twig');
        }

        $logs = $em->getRepository(\App\Entity\ActivityLog::class)
            ->findBy([], ['createdAt' => 'DESC']);

        $data = [];

        foreach ($logs as $log) {
            $details = $log->getDetails() ?? '';
            $role = 'Unknown';
            if (preg_match('/Role:\s*(Admin|Staff)/i', $details, $matches) === 1) {
                $role = ucfirst(strtolower($matches[1]));
            }

            $data[] = [
                'action'    => $log->getAction(),
                'user'      => $log->getUser(),
                'role'      => $role,
                'details'   => $details,
                'createdAt' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }


        return $this->json($data);
    }
}
