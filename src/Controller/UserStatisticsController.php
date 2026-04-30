<?php

namespace App\Controller;

use App\Service\SearchHistoryService;
use App\Service\ValidationService;
use App\Service\UserLoginService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller exposing personal statistics for authenticated users.
 *
 * All actions perform an authentication check using AuthService::isAuthenticated().
 * If the user is not logged in, they are redirected to the login page – this
 * mitigates IDOR (Insecure Direct Object Reference) by ensuring only the
 * owner can view their own data.
 */
class UserStatisticsController extends AbstractController
{
    public function __construct(
        private readonly SearchHistoryService $searchHistoryService,
        private readonly UserLoginService $userLoginService,
        private readonly ValidationService $validationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Ensure the request is made by an authenticated user.
     */
    private function ensureAuthenticated(Request $request): ?Response
    {
        $sessionUser = $request->getSession()->get('auth_user');
        if (!$sessionUser || !isset($sessionUser['id'])) {
            return $this->redirectToRoute('auth_login');
        }
        // No further admin check – this is a personal view.
        return null;
    }

    #[Route('/user/search-history', name: 'user_search_history', methods: ['GET'])]
    public function searchHistory(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated($request)) {
            return $response;
        }

        $sessionUser = $request->getSession()->get('auth_user');
        $userId = (int) $sessionUser['id'];

        // Optional pagination – validated with ValidationService.
        $page = (int) $request->query->get('page', '1');
        $limit = (int) $request->query->get('limit', '20');
        $this->validationService->clearErrors()
            ->validateNumber($page, 'page', 1)
            ->validateNumber($limit, 'limit', 1);
        if (!$this->validationService->isValid()) {
            $this->logger->warning('Invalid pagination parameters for user search history');
            $page = 1;
            $limit = 20;
        }

        // Retrieve only the current user's history.
        $rawHistories = $this->searchHistoryService->getUserSearchHistory($userId);
        // Apply simple pagination manually.
        $offset = ($page - 1) * $limit;
        $histories = array_slice($rawHistories, $offset, $limit);
        $histories = array_map(fn($h) => [
            'userId' => $h->getUserId(),
            'query' => $h->getSearchQuery(),
            'type' => $h->getSearchType(),
            'resultsFound' => $h->getResultsFound(),
            'createdAt' => $h->getSearchTime(),
        ], $histories);

        return $this->render('user/search_history.html.twig', [
            'histories' => $histories,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/user/login-stats', name: 'user_login_stats', methods: ['GET'])]
    public function loginStats(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated($request)) {
            return $response;
        }

        $sessionUser = $request->getSession()->get('auth_user');
        $userId = (int) $sessionUser['id'];

        // Retrieve only the current user's login records.
        $logins = $this->userLoginService->getUserLogins($userId);
        $stats = array_map(fn($l) => [
            'userId' => $l->getUserId(),
            'loginTime' => $l->getLoginTime(),
            'loginMethod' => $l->getLoginMethod(),
            'ipAddress' => $l->getIpAddress(),
            'userAgent' => $l->getUserAgent(),
        ], $logins);

        return $this->render('user/login_stats.html.twig', [
            'stats' => $stats,
        ]);
    }
}
