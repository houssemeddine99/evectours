<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\SearchHistoryService;
use App\Service\UserLoginService;
use App\Service\VoyageVisitService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller exposing statistical data for admin users.
 *
 * All actions perform a basic admin check using AuthService::isAdmin().
 * If the current session user is not an admin, a redirect to the login page
 * is performed – this mitigates IDOR (Insecure Direct Object Reference)
 * by ensuring only authorised users can access the endpoints.
 */
class StatisticsController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly SearchHistoryService $searchHistoryService,
        private readonly UserLoginService $userLoginService,
        private readonly VoyageVisitService $voyageVisitService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Helper to ensure the current session user is an admin.
     */
    private function ensureAdmin(Request $request): ?Response
    {
        $sessionUser = $request->getSession()->get('auth_user');
        if (!$sessionUser || !isset($sessionUser['id'])) {
            return $this->redirectToRoute('auth_login');
        }
        if (!$this->authService->isAdmin((int) $sessionUser['id'])) {
            // Log the unauthorized attempt
            $this->logger->warning('Non‑admin attempted to access admin statistics', [
                'user_id' => $sessionUser['id'] ?? null,
                'ip' => $request->getClientIp(),
            ]);
            return $this->redirectToRoute('auth_login');
        }
        return null;
    }

#[Route('/admin/voyage-visits', name: 'admin_voyage_visits', methods: ['GET'])]
public function voyageVisits(Request $request): Response
{
    if ($response = $this->ensureAdmin($request)) {
        return $response;
    }

    $page = $request->query->getInt('page', 1);
    $limit = 20;

    // Chart data now uses names
    $mostVisited = $this->voyageVisitService->getMostVisitedVoyages(10);
    $chartLabels = array_map(fn($v) => $v['voyageName'], $mostVisited);
    $chartData = array_map(fn($v) => (int)$v['visitCount'], $mostVisited);

    $pagination = $this->voyageVisitService->getPaginatedVisits($page, $limit);

    return $this->render('admin/voyage_visits.html.twig', [
        'visits' => $pagination['data'], // This now contains [0 => VoyageVisit object, 'voyageName' => '...']
        'currentPage' => $pagination['currentPage'],
        'totalPages' => $pagination['totalPages'],
        'totalItems' => $pagination['totalItems'],
        'chartLabels' => $chartLabels,
        'chartData' => $chartData,
    ]);
}
    #[Route('/admin/search-history', name: 'admin_search_history', methods: ['GET'])]
    public function searchHistory(Request $request): Response
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 50);

        // Get the specific slice of data from the service
        $paginationData = $this->searchHistoryService->getPaginatedSearchHistory($page, $limit);

        // Map the 'data' portion of the results
        $histories = array_map(function ($h) {
            return [
                'userId' => $h->getUserId(),
                'query' => $h->getSearchQuery(),
                'type' => $h->getSearchType(),
                'resultsFound' => $h->getResultsFound(),
                'createdAt' => $h->getSearchTime(),
            ];
        }, $paginationData['data']);
        $typeCounts = [];
        foreach ($histories as $h) {
            $type = $h['type'] ?: 'Unknown';
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
        }

        return $this->render('admin/search_history.html.twig', [
            'histories' => $histories,
            'currentPage' => $paginationData['currentPage'],
            'totalPages' => $paginationData['totalPages'],
            'totalItems' => $paginationData['totalItems'],
            'limit' => $limit,
            // Add these for the graph
            'chartLabels' => array_keys($typeCounts),
            'chartData' => array_values($typeCounts),
        ]);
    }

    #[Route('/admin/login-stats', name: 'admin_login_stats', methods: ['GET'])]
    public function loginStats(Request $request): Response
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $page = $request->query->getInt('page', 1);
        $limit = 100; // The "Average" recommended rows

        // You should update your service to support findPaginated or similar
        $logins = $this->userLoginService->getPaginatedLogins($page, $limit);

        $stats = array_map(function ($l) {
            return [
                'userId' => $l->getUserId(),
                'loginTime' => $l->getLoginTime(),
                'loginMethod' => $l->getLoginMethod(),
                'ipAddress' => $l->getIpAddress(),
                'userAgent' => $l->getUserAgent(),
            ];
        }, $logins['data']);

        $chartRaw = [];
        foreach ($stats as $entry) {
            $date = $entry['loginTime']->format('Y-m-d');
            $chartRaw[$date] = ($chartRaw[$date] ?? 0) + 1;
        }

        // 2. Sort dates so the graph flows left-to-right
        ksort($chartRaw);

        return $this->render('admin/login_stats.html.twig', [
            'stats' => $stats,
            'currentPage' => $page,
            // Pass these to Twig
            'chartLabels' => array_keys($chartRaw),
            'chartData' => array_values($chartRaw),
        ]);
    }
}
