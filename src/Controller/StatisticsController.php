<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\ActivityService;
use App\Service\SearchHistoryService;
use App\Service\UserLoginService;
use App\Service\ValidationService;
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
        private readonly ActivityService $activityService,
        private readonly SearchHistoryService $searchHistoryService,
        private readonly UserLoginService $userLoginService,
        private readonly ValidationService $validationService,
        private readonly LoggerInterface $logger,
    ) {
    }

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


    #[Route('/admin/search-history', name: 'admin_search_history', methods: ['GET'])]
    public function searchHistory(Request $request): Response
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        // Optional pagination parameters
        $page = (int) $request->query->get('page', '1');
        $limit = (int) $request->query->get('limit', '20');

        // Validate pagination values (positive integers)
        $this->validationService->clearErrors()
            ->validateNumber($page, 'page', 1)
            ->validateNumber($limit, 'limit', 1);

        if (!$this->validationService->isValid()) {
            $this->logger->warning('Invalid pagination parameters for admin search history');
            // Fallback to defaults
            $page = 1;
            $limit = 20;
        }

        // Fetch all search history records for admin view and map to expected array structure
        $rawHistories = $this->searchHistoryService->getAllSearchHistory();
        $histories = array_map(function ($h) {
            return [
                'userId' => $h->getUserId(),
                'query' => $h->getSearchQuery(),
                'type' => $h->getSearchType(),
                'resultsFound' => $h->getResultsFound(),
                'createdAt' => $h->getSearchTime(),
            ];
        }, $rawHistories);

        return $this->render('admin/search_history.html.twig', [
            'histories' => $histories,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/admin/login-stats', name: 'admin_login_stats', methods: ['GET'])]
    public function loginStats(Request $request): Response
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        // Fetch all login records for detailed admin view
        $logins = $this->userLoginService->getAllLogins();
        // Map entities to array for Twig rendering
        $stats = array_map(function ($l) {
            return [
                'userId' => $l->getUserId(),
                'loginTime' => $l->getLoginTime(),
                'loginMethod' => $l->getLoginMethod(),
                'ipAddress' => $l->getIpAddress(),
                'userAgent' => $l->getUserAgent(),
            ];
        }, $logins);
        return $this->render('admin/login_stats.html.twig', [
            'stats' => $stats,
        ]);
    }
}
