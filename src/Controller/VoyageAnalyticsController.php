<?php

namespace App\Controller;

use App\Repository\VoyageVisitRepository;
use App\Service\VoyageVisitService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoyageAnalyticsController extends AbstractController
{
    public function __construct(
        private readonly VoyageVisitService $voyageVisitService,
        private readonly VoyageVisitRepository $voyageVisitRepository,
        private readonly AdminController $adminController,
    ) {}

    #[Route('/admin/analytics/voyages', name: 'admin_voyage_analytics', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $topVoyages = $this->voyageVisitService->getMostVisitedVoyages(10);
        $sourceBreakdown = $this->voyageVisitRepository->getSourceBreakdown();
        $dailyVisits = $this->voyageVisitRepository->getVisitsByDay(30);
        $totalVisits = $this->voyageVisitRepository->getTotalVisits();

        return $this->render('admin/voyage_analytics.html.twig', [
            'top_voyages' => $topVoyages,
            'source_breakdown' => $sourceBreakdown,
            'daily_visits' => $dailyVisits,
            'total_visits' => $totalVisits,
        ]);
    }
}
