<?php

namespace App\Controller;

use App\Service\VoyageService;
use App\Service\OfferService;
use App\Service\ActivityService;
use App\Service\VoyageImageService;
use App\Service\ValidationService;
use App\Service\SearchHistoryService;
use App\Service\VoyageVisitService;
use App\Service\TagService;
use App\Service\AiVoyageService;
use App\Service\FavoriteService;
use App\Service\ReviewService;
use App\Repository\ReviewRepository;
use App\Repository\VoyageRepository;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoyageController extends AbstractController
{
    public function __construct(
        private readonly VoyageService $voyageService,
        private readonly OfferService $offerService,
        private readonly ActivityService $activityService,
        private readonly VoyageImageService $voyageImageService,
        private readonly VoyageRepository $voyageRepository,
        private readonly ValidationService $validationService,
        private readonly SearchHistoryService $searchHistoryService,
        private readonly VoyageVisitService $voyageVisitService,
        private readonly AdminController $adminController,
        private readonly TagService $tagService,
        private readonly AiVoyageService $aiVoyageService,
        private readonly FavoriteService $favoriteService,
        private readonly ReviewService $reviewService,
        private readonly ReviewRepository $reviewRepository,
        #[Target('cache.api_external')]
        private readonly CacheInterface $cache,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    #[Route('/', name: 'travel_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('travel/home.html.twig', [
            'active_nav' => 'home',
            'featured_voyages' => $this->voyageService->getFeaturedVoyages(6),
        ]);
    }

    #[Route('/voyages', name: 'travel_voyages', methods: ['GET'])]
    public function voyages(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 12;

        $search = $request->query->get('search', '');
        $minPrice = $request->query->get('min_price');
        $maxPrice = $request->query->get('max_price');
        $sortBy = $request->query->get('sort_by', 'startDate');
        $sortOrder = $request->query->get('sort_order', 'ASC');
        $tagFilter = $request->query->get('tag', '');

        $filters = [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ];

        if (!empty($search)) {
            $filters['search'] = $search;
        }
        if (!empty($minPrice)) {
            $filters['min_price'] = $minPrice;
        }
        if (!empty($maxPrice)) {
            $filters['max_price'] = $maxPrice;
        }
        if (!empty($tagFilter)) {
            $filters['tag'] = $tagFilter;
        }

        $hasFilters = !empty($search) || !empty($minPrice) || !empty($maxPrice) || !empty($tagFilter);

        if ($hasFilters) {
            $this->logger?->info('Public searching voyages', $filters);
            $voyages = $this->voyageService->searchVoyages($filters);
            $totalVoyages = $this->voyageService->countSearchResults($filters);
        } else {
            $voyages = $this->voyageService->getVoyages($page, $limit);
            $totalVoyages = $this->voyageService->getTotalVoyages();
        }

        if (!empty($search)) {
            $sessionUser = $request->getSession()->get('auth_user');
            $userId = $sessionUser['id'] ?? 0;
            $resultsFound = count($voyages);
            $this->searchHistoryService->recordSearch($userId, $search, 'voyage', $resultsFound);
        }

        $totalPages = ceil($totalVoyages / $limit) ?: 1;

        $sessionUser = $request->getSession()->get('auth_user');
        $userId = $sessionUser['id'] ?? 0;
        $favoriteIds = $userId > 0 ? $this->favoriteService->getFavoriteVoyageIds($userId) : [];

        return $this->render('travel/voyages.html.twig', [
            'active_nav' => 'voyages',
            'voyages' => $voyages,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'search' => $search,
            'filters' => $filters,
            'all_tags' => $this->tagService->getAllTags(),
            'active_tag' => $tagFilter,
            'user_id' => $userId,
            'favorite_ids' => $favoriteIds,
        ]);
    }

    #[Route('/voyages/{slug}', name: 'travel_voyage_detail', methods: ['GET'])]
    public function voyageDetail(Request $request, string $slug): Response
    {
        $voyage = $this->voyageService->getVoyageBySlug($slug);

        if ($voyage === null) {
            throw $this->createNotFoundException('Voyage not found');
        }

        $sessionUser = $request->getSession()->get('auth_user');
        $userId = ($sessionUser && isset($sessionUser['id'])) ? (int) $sessionUser['id'] : 1;

        $this->voyageVisitService->recordVisit($userId, $voyage['id'], 'detail');

        $offers = $this->offerService->getActiveOffers();
        $offerForVoyage = array_filter($offers, fn($o) => (int) $o['voyage_id'] === $voyage['id']);
        $offer = $offerForVoyage ? array_values($offerForVoyage)[0] : null;

        $isFavorite = false;
        if ($userId > 1) {
            $favIds = $request->getSession()->get('favorite_ids_' . $userId, null);
            if ($favIds === null) {
                $isFavorite = false;
            } else {
                $isFavorite = in_array($voyage['id'], $favIds, true);
            }
        }

        $compareList = $request->getSession()->get('compare_list', []);

        $startDate = $voyage['start_date'] ? new \DateTime($voyage['start_date']) : null;
        $endDate = $voyage['end_date'] ? new \DateTime($voyage['end_date']) : null;
        $durationDays = ($startDate && $endDate) ? (int) $startDate->diff($endDate)->days : 5;

        return $this->render('travel/voyage_detail.html.twig', [
            'active_nav' => 'voyages',
            'voyage' => $voyage,
            'offer' => $offer,
            'is_favorite' => $isFavorite,
            'compare_list' => $compareList,
            'duration_days' => $durationDays,
            'user_id' => $userId,
            'reviews' => $this->reviewService->getReviewsForVoyage($voyage['id']),
            'review_avg' => $this->reviewService->getAverageRating($voyage['id']),
            'review_count' => $this->reviewService->getReviewCount($voyage['id']),
            'user_review' => $userId > 1 ? $this->reviewService->getUserReview($userId, $voyage['id']) : null,
        ]);
    }

    #[Route('/api/voyage-meta', name: 'api_voyage_meta', methods: ['GET'])]
    public function apiVoyageMeta(Request $request): JsonResponse
    {
        $destination = trim((string) $request->query->get('destination', ''));
        if ($destination === '') {
            return $this->json(null);
        }
        return $this->json($this->fetchCountryInfo($destination));
    }

    #[Route('/admin/voyages/ai/description', name: 'admin_voyage_ai_description', methods: ['POST'])]
    public function aiGenerateDescription(Request $request): JsonResponse
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $title = $request->request->get('title', '');
        $destination = $request->request->get('destination', '');
        $existing = $request->request->get('description');
        $duration = $request->request->getInt('duration_days', 5);

        if (empty($title) || empty($destination)) {
            return $this->json(['error' => 'Title and destination are required'], 400);
        }

        $description = $this->aiVoyageService->generateDescription($title, $destination, $duration, $existing ?: null);

        if ($description === null) {
            return $this->json(['error' => 'AI service unavailable'], 503);
        }

        return $this->json(['description' => $description]);
    }

    #[Route('/voyages/{slug}/itinerary', name: 'voyage_ai_itinerary', methods: ['GET'])]
    public function aiItinerary(Request $request, string $slug): JsonResponse
    {
        $voyage = $this->voyageService->getVoyageBySlug($slug);
        if ($voyage === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $startDate = $voyage['start_date'] ? new \DateTime($voyage['start_date']) : null;
        $endDate = $voyage['end_date'] ? new \DateTime($voyage['end_date']) : null;
        $durationDays = ($startDate && $endDate) ? (int) $startDate->diff($endDate)->days : 5;
        $durationDays = max(1, $durationDays);

        $itinerary = $this->aiVoyageService->generateItinerary($voyage['title'], $voyage['destination'], $durationDays);

        if ($itinerary === null) {
            return $this->json(['error' => 'AI service unavailable'], 503);
        }

        return $this->json(['itinerary' => $itinerary]);
    }

    #[Route('/voyages/{id}/review', name: 'voyage_submit_review', methods: ['POST'])]
    public function submitReview(Request $request, int $id): Response
    {
        $sessionUser = $request->getSession()->get('auth_user');
        if (!$sessionUser || empty($sessionUser['id'])) {
            return $this->redirectToRoute('auth_login');
        }

        $userId = (int) $sessionUser['id'];
        $rating = (int) $request->request->get('rating', 5);
        $comment = trim((string) $request->request->get('comment', ''));

        $this->reviewService->submitReview($userId, $id, $rating, $comment ?: null);

        $voyage = $this->voyageRepository->find($id);
        $slug = $voyage?->getSlug() ?? (string) $id;

        $this->addFlash('success', 'Your review has been saved. Thank you!');
        return $this->redirectToRoute('travel_voyage_detail', ['slug' => $slug]);
    }

    #[Route('/api/voyages/{id}/reviews', name: 'api_voyage_reviews', methods: ['GET'])]
    public function apiVoyageReviews(int $id): JsonResponse
    {
        return $this->json([
            'reviews'      => $this->reviewService->getReviewsForVoyage($id),
            'average'      => $this->reviewService->getAverageRating($id),
            'count'        => $this->reviewService->getReviewCount($id),
        ]);
    }

    // ==================== ADMIN REVIEWS ====================

    #[Route('/admin/reviews', name: 'admin_reviews', methods: ['GET'])]
    public function adminReviews(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $voyageId = $request->query->getInt('voyage_id', 0);

        if ($voyageId > 0) {
            $reviews = $this->reviewService->getReviewsForVoyage($voyageId);
            $voyage  = $this->voyageRepository->find($voyageId);
        } else {
            $reviews = $this->reviewRepository->findAllWithVoyage();
            $voyage  = null;
        }

        return $this->render('admin/reviews.html.twig', [
            'reviews'    => $reviews,
            'voyage'     => $voyage,
            'voyage_id'  => $voyageId ?: null,
        ]);
    }

    #[Route('/admin/reviews/{id}/delete', name: 'admin_review_delete', methods: ['POST'])]
    public function adminDeleteReview(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $this->reviewRepository->deleteById($id);
        $this->addFlash('success', 'Review deleted.');

        $referer = $request->headers->get('referer', $this->generateUrl('admin_reviews'));
        return $this->redirect($referer);
    }

    // ==================== ADMIN VOYAGES ====================

    #[Route('/admin/voyages', name: 'admin_voyages', methods: ['GET'])]
    public function adminVoyages(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $filters = $this->buildSearchFilters($request);

        if ($this->hasActiveSearchFilters($filters)) {
            $this->logger?->info('Admin searching voyages', $filters);
            $voyages = $this->voyageService->searchVoyages($filters);
        } else {
            $voyages = $this->voyageService->getAllVoyagesForAdmin();
        }

        return $this->render('admin/voyages.html.twig', [
            'voyages' => $voyages,
            'search' => $request->query->get('search', ''),
            'filters' => $filters,
        ]);
    }

    #[Route('/admin/voyages/new', name: 'admin_voyage_new', methods: ['GET', 'POST'])]
    public function adminNewVoyage(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->validationService->validateVoyage($data);
            if (!$this->validationService->isValid()) {
                $this->logger?->warning('Validation failed for new voyage', $this->validationService->getErrors());
                foreach ($this->validationService->getErrors() as $field => $errors) {
                    foreach ($errors as $error) {
                        $this->addFlash('error', $error);
                    }
                }
                return $this->render('admin/voyage_form.html.twig', [
                    'voyage' => $data,
                    'voyages' => $this->voyageRepository->findAll(),
                    'all_tags' => $this->tagService->getAllTags(),
                    'errors' => $this->validationService->getErrors(),
                ]);
            }

            $this->logger?->info('Creating new voyage', ['title' => $data['title'] ?? '']);
            $voyage = $this->voyageService->createVoyage($data);
            $tagIds = $data['tags'] ?? [];
            if (!empty($tagIds)) {
                $this->tagService->syncVoyageTags($voyage, $tagIds);
            }
            $this->addFlash('success', 'Voyage created successfully!');
            return $this->redirectToRoute('admin_voyages');
        }

        return $this->render('admin/voyage_form.html.twig', [
            'voyage' => null,
            'voyages' => $this->voyageRepository->findAll(),
            'all_tags' => $this->tagService->getAllTags(),
        ]);
    }

    #[Route('/admin/voyages/{id}/manage', name: 'admin_voyage_manage', methods: ['GET'])]
    public function adminManageVoyage(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $voyage = $this->voyageService->getVoyageByIdForAdmin($id);
        if (!$voyage) {
            throw $this->createNotFoundException('Voyage not found');
        }

        $offers = $this->filterByEntityId($this->offerService->getAllOffersForAdmin(), $id);
        $activities = $this->filterByEntityId($this->activityService->getAllActivitiesForAdmin(), $id);
        $images = $this->filterByEntityId($this->voyageImageService->getAllImagesForAdmin(), $id);

        return $this->render('admin/voyage_manage.html.twig', [
            'voyage' => $voyage,
            'offers' => $offers,
            'activities' => $activities,
            'images' => $images,
            'voyage_id' => $id,
        ]);
    }

    #[Route('/admin/voyages/{id}/edit', name: 'admin_voyage_edit', methods: ['GET', 'POST'])]
    public function adminEditVoyage(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $voyage = $this->voyageService->getVoyageByIdForAdmin($id);
        if (!$voyage) {
            throw $this->createNotFoundException('Voyage not found');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->voyageService->updateVoyage($id, $data);

            $tagIds = $data['tags'] ?? [];
            $voyageEntity = $this->voyageRepository->find($id);
            if ($voyageEntity) {
                $this->tagService->syncVoyageTags($voyageEntity, $tagIds);
            }

            $this->addFlash('success', 'Voyage updated successfully!');
            return $this->redirectToRoute('admin_voyages');
        }

        return $this->render('admin/voyage_form.html.twig', [
            'voyage' => $voyage,
            'voyages' => $this->voyageRepository->findAll(),
            'all_tags' => $this->tagService->getAllTags(),
        ]);
    }

    #[Route('/admin/voyages/{id}/delete', name: 'admin_voyage_delete', methods: ['GET', 'POST'])]
    public function adminDeleteVoyage(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $this->voyageService->deleteVoyage($id);
        $this->addFlash('success', 'Voyage deleted successfully!');
        return $this->redirectToRoute('admin_voyages');
    }

    #[Route('/admin/tags', name: 'admin_tags', methods: ['GET', 'POST'])]
    public function adminTags(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $color = trim((string) $request->request->get('color', '')) ?: null;
            if (!empty($name)) {
                $this->tagService->createTag($name, $color);
                $this->addFlash('success', "Tag \"{$name}\" created.");
            }
            return $this->redirectToRoute('admin_tags');
        }

        return $this->render('admin/tags.html.twig', [
            'tags' => $this->tagService->getAllTags(),
        ]);
    }

    #[Route('/admin/tags/{id}/delete', name: 'admin_tag_delete', methods: ['POST'])]
    public function adminDeleteTag(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $this->tagService->deleteTag($id);
        $this->addFlash('success', 'Tag deleted.');
        return $this->redirectToRoute('admin_tags');
    }

    // ==================== HELPER METHODS ====================

    private function fetchCountryInfo(string $destination): ?array
    {
        $parts = array_map('trim', explode(',', $destination));
        $country = end($parts);
        if (empty($country)) {
            return null;
        }

        return $this->cache->get('country_' . md5($destination), function (ItemInterface $item) use ($country): ?array {
            $item->expiresAfter(86400); // 24 hours

            $url = 'https://restcountries.com/v3.1/name/' . urlencode($country) . '?fields=name,flags,languages,currencies,timezones,capital,flag';
            $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                return null;
            }

            $data = json_decode($raw, true);
            if (!is_array($data) || empty($data) || isset($data['status'])) {
                return null;
            }

            $c = reset($data);
            if (!is_array($c)) {
                return null;
            }
            $currencies = $c['currencies'] ?? [];
            $currencyInfo = !empty($currencies) ? array_values($currencies)[0] : null;
            $languages = array_values($c['languages'] ?? []);

            return [
                'name'       => $c['name']['common'] ?? $country,
                'flag_svg'   => $c['flags']['svg'] ?? ($c['flags']['png'] ?? null),
                'flag_emoji' => $c['flag'] ?? null,
                'capital'    => $c['capital'][0] ?? null,
                'language'   => $languages[0] ?? null,
                'currency'   => $currencyInfo ? ($currencyInfo['name'] . ' (' . ($currencyInfo['symbol'] ?? '') . ')') : null,
                'timezone'   => $c['timezones'][0] ?? null,
            ];
        });
    }

    private function buildSearchFilters(Request $request): array
    {
        $search = $request->query->get('search', '');
        $minPrice = $request->query->get('min_price');
        $maxPrice = $request->query->get('max_price');
        $startDateFrom = $request->query->get('start_date_from');
        $startDateTo = $request->query->get('start_date_to');
        $sortBy = $request->query->get('sort_by', 'startDate');
        $sortOrder = $request->query->get('sort_order', 'ASC');

        $filters = [];

        if (!empty($search)) {
            $filters['title'] = $search;
            $filters['destination'] = $search;
        }
        if (!empty($minPrice)) {
            $filters['min_price'] = $minPrice;
        }
        if (!empty($maxPrice)) {
            $filters['max_price'] = $maxPrice;
        }
        if (!empty($startDateFrom)) {
            $filters['start_date_from'] = $startDateFrom;
        }
        if (!empty($startDateTo)) {
            $filters['start_date_to'] = $startDateTo;
        }

        $filters['sort_by'] = $sortBy;
        $filters['sort_order'] = $sortOrder;

        return $filters;
    }

    private function hasActiveSearchFilters(array $filters): bool
    {
        return !empty($filters['title'])
            || !empty($filters['destination'])
            || !empty($filters['min_price'])
            || !empty($filters['max_price'])
            || !empty($filters['start_date_from'])
            || !empty($filters['start_date_to']);
    }

    private function filterByEntityId(array $entities, int $voyageId): array
    {
        return array_values(array_filter($entities, fn($e) => (int) $e['voyage_id'] === $voyageId));
    }
}
