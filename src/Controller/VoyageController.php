<?php

namespace App\Controller;

use App\Service\VoyageService;
use App\Service\OfferService;
use App\Service\ActivityService;
use App\Service\VoyageImageService;
use App\Service\ValidationService;
use App\Service\SearchHistoryService;
use App\Service\VoyageVisitService;
use App\Service\AiVoyageService;
use App\Service\FavoriteService;
use App\Service\ReviewService;
use App\Service\CarbonFootprintService;
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
        private readonly AiVoyageService $aiVoyageService,
        private readonly FavoriteService $favoriteService,
        private readonly ReviewService $reviewService,
        private readonly CarbonFootprintService $carbonService,
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
        $hasFilters = !empty($search) || !empty($minPrice) || !empty($maxPrice);

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
        $compareList = $request->getSession()->get('compare_list', []);

        return $this->render('travel/voyages.html.twig', [
            'active_nav' => 'voyages',
            'voyages' => $voyages,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'search' => $search,
            'filters' => $filters,
            'user_id' => $userId,
            'favorite_ids' => $favoriteIds,
            'compare_list' => $compareList,
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
        $userId = ($sessionUser && isset($sessionUser['id'])) ? (int) $sessionUser['id'] : 0;

        $this->voyageVisitService->recordVisit($userId, $voyage['id'], 'detail');

        $offers = $this->offerService->getActiveOffers();
        $offerForVoyage = array_filter($offers, fn($o) => (int) $o['voyage_id'] === $voyage['id']);
        $offer = $offerForVoyage ? array_values($offerForVoyage)[0] : null;

        $isFavorite = $userId > 0 && $this->favoriteService->isFavorite($userId, $voyage['id']);

        $compareList = $request->getSession()->get('compare_list', []);

        $startDate = $voyage['start_date'] ? new \DateTime($voyage['start_date']) : null;
        $endDate = $voyage['end_date'] ? new \DateTime($voyage['end_date']) : null;
        // Prefer an explicit duration; otherwise derive from the date range; else default.
        $durationDays = $voyage['duration_days'] ?? null;
        if ($durationDays === null) {
            $durationDays = ($startDate && $endDate) ? (int) $startDate->diff($endDate)->days : 5;
        }

        $carbon = $this->carbonService->calculate($voyage['destination'] ?? '', 1);

        return $this->render('travel/voyage_detail.html.twig', [
            'active_nav' => 'voyages',
            'voyage' => $voyage,
            'offer' => $offer,
            'is_favorite' => $isFavorite,
            'compare_list' => $compareList,
            'duration_days' => $durationDays,
            'user_id' => $userId,
            'carbon' => $carbon,
            'reviews' => $this->reviewService->getReviewsForVoyage($voyage['id']),
            'review_avg' => $this->reviewService->getAverageRating($voyage['id']),
            'review_count' => $this->reviewService->getReviewCount($voyage['id']),
            'user_review' => $userId > 0 ? $this->reviewService->getUserReview($userId, $voyage['id']) : null,
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

        $title = (string) $request->request->get('title', '');
        $destination = (string) $request->request->get('destination', '');
        $existing = $request->request->get('description');
        $duration = $request->request->getInt('duration_days', 5);

        if (empty($title) || empty($destination)) {
            return $this->json(['error' => 'Title and destination are required'], 400);
        }

        $existingStr = is_string($existing) ? $existing : null;
        $description = $this->aiVoyageService->generateDescription($title, $destination, $duration, $existingStr ?: null);

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
        $durationDays = $voyage['duration_days'] ?? null;
        if ($durationDays === null) {
            $durationDays = ($startDate && $endDate) ? (int) $startDate->diff($endDate)->days : 5;
        }
        $durationDays = max(1, (int) $durationDays);

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
                    'errors' => $this->validationService->getErrors(),
                ]);
            }

            $this->logger?->info('Creating new voyage', ['title' => $data['title'] ?? '']);
            $this->voyageService->createVoyage($data);
            $this->addFlash('success', 'Voyage created successfully!');
            return $this->redirectToRoute('admin_voyages');
        }

        return $this->render('admin/voyage_form.html.twig', [
            'voyage' => null,
            'voyages' => $this->voyageRepository->findAll(),
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

            $this->addFlash('success', 'Voyage updated successfully!');
            return $this->redirectToRoute('admin_voyages');
        }

        return $this->render('admin/voyage_form.html.twig', [
            'voyage' => $voyage,
            'voyages' => $this->voyageRepository->findAll(),
        ]);
    }

    #[Route('/admin/voyages/{id}/delete', name: 'admin_voyage_delete', methods: ['POST'])]
    public function adminDeleteVoyage(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $this->voyageService->deleteVoyage($id);
        $this->addFlash('success', 'Voyage deleted successfully!');
        return $this->redirectToRoute('admin_voyages');
    }

    // ==================== HELPER METHODS ====================

    /** @return array<string, mixed>|null */
    /**
     * Built-in country facts for the destinations the agency serves.
     * Self-contained (no external API — restcountries v3.1 was deprecated).
     * Flag images via flagcdn.com; flag emoji computed from the ISO code.
     * @var array<string, array{code:string,name:string,capital:string,language:string,currency:string,timezone:string}>
     */
    private const COUNTRY_DATA = [
        'saudi arabia'         => ['code' => 'sa', 'name' => 'Saudi Arabia',         'capital' => 'Riyadh',           'language' => 'Arabic',     'currency' => 'Saudi Riyal (SAR)',       'timezone' => 'UTC+03:00'],
        'tunisia'              => ['code' => 'tn', 'name' => 'Tunisia',              'capital' => 'Tunis',            'language' => 'Arabic',     'currency' => 'Tunisian Dinar (TND)',    'timezone' => 'UTC+01:00'],
        'united arab emirates' => ['code' => 'ae', 'name' => 'United Arab Emirates', 'capital' => 'Abu Dhabi',        'language' => 'Arabic',     'currency' => 'UAE Dirham (AED)',        'timezone' => 'UTC+04:00'],
        'qatar'                => ['code' => 'qa', 'name' => 'Qatar',                'capital' => 'Doha',             'language' => 'Arabic',     'currency' => 'Qatari Riyal (QAR)',      'timezone' => 'UTC+03:00'],
        'jordan'               => ['code' => 'jo', 'name' => 'Jordan',               'capital' => 'Amman',            'language' => 'Arabic',     'currency' => 'Jordanian Dinar (JOD)',   'timezone' => 'UTC+03:00'],
        'egypt'                => ['code' => 'eg', 'name' => 'Egypt',                'capital' => 'Cairo',            'language' => 'Arabic',     'currency' => 'Egyptian Pound (EGP)',    'timezone' => 'UTC+02:00'],
        'morocco'              => ['code' => 'ma', 'name' => 'Morocco',              'capital' => 'Rabat',            'language' => 'Arabic',     'currency' => 'Moroccan Dirham (MAD)',   'timezone' => 'UTC+01:00'],
        'turkey'               => ['code' => 'tr', 'name' => 'Türkiye',              'capital' => 'Ankara',           'language' => 'Turkish',    'currency' => 'Turkish Lira (₺)',        'timezone' => 'UTC+03:00'],
        'france'               => ['code' => 'fr', 'name' => 'France',               'capital' => 'Paris',            'language' => 'French',     'currency' => 'Euro (€)',                'timezone' => 'UTC+01:00'],
        'italy'                => ['code' => 'it', 'name' => 'Italy',                'capital' => 'Rome',             'language' => 'Italian',    'currency' => 'Euro (€)',                'timezone' => 'UTC+01:00'],
        'spain'                => ['code' => 'es', 'name' => 'Spain',                'capital' => 'Madrid',           'language' => 'Spanish',    'currency' => 'Euro (€)',                'timezone' => 'UTC+01:00'],
        'germany'              => ['code' => 'de', 'name' => 'Germany',              'capital' => 'Berlin',           'language' => 'German',     'currency' => 'Euro (€)',                'timezone' => 'UTC+01:00'],
        'netherlands'          => ['code' => 'nl', 'name' => 'Netherlands',          'capital' => 'Amsterdam',        'language' => 'Dutch',      'currency' => 'Euro (€)',                'timezone' => 'UTC+01:00'],
        'austria'              => ['code' => 'at', 'name' => 'Austria',              'capital' => 'Vienna',           'language' => 'German',     'currency' => 'Euro (€)',                'timezone' => 'UTC+01:00'],
        'portugal'             => ['code' => 'pt', 'name' => 'Portugal',             'capital' => 'Lisbon',           'language' => 'Portuguese', 'currency' => 'Euro (€)',                'timezone' => 'UTC+00:00'],
        'greece'               => ['code' => 'gr', 'name' => 'Greece',               'capital' => 'Athens',           'language' => 'Greek',      'currency' => 'Euro (€)',                'timezone' => 'UTC+02:00'],
        'malta'                => ['code' => 'mt', 'name' => 'Malta',                'capital' => 'Valletta',         'language' => 'Maltese',    'currency' => 'Euro (€)',                'timezone' => 'UTC+01:00'],
        'czech republic'       => ['code' => 'cz', 'name' => 'Czech Republic',       'capital' => 'Prague',           'language' => 'Czech',      'currency' => 'Czech Koruna (Kč)',       'timezone' => 'UTC+01:00'],
        'united kingdom'       => ['code' => 'gb', 'name' => 'United Kingdom',        'capital' => 'London',           'language' => 'English',    'currency' => 'Pound Sterling (£)',      'timezone' => 'UTC+00:00'],
        'united states'        => ['code' => 'us', 'name' => 'United States',         'capital' => 'Washington, D.C.', 'language' => 'English',    'currency' => 'US Dollar ($)',           'timezone' => 'UTC−05:00'],
        'canada'               => ['code' => 'ca', 'name' => 'Canada',               'capital' => 'Ottawa',           'language' => 'English / French', 'currency' => 'Canadian Dollar (C$)', 'timezone' => 'UTC−05:00'],
        'japan'                => ['code' => 'jp', 'name' => 'Japan',                'capital' => 'Tokyo',            'language' => 'Japanese',   'currency' => 'Japanese Yen (¥)',        'timezone' => 'UTC+09:00'],
        'australia'            => ['code' => 'au', 'name' => 'Australia',            'capital' => 'Canberra',         'language' => 'English',    'currency' => 'Australian Dollar (A$)',  'timezone' => 'UTC+10:00'],
    ];

    /** @var array<string, string> common aliases → canonical key */
    private const COUNTRY_ALIASES = [
        'ksa' => 'saudi arabia', 'uae' => 'united arab emirates', 'emirates' => 'united arab emirates',
        'uk' => 'united kingdom', 'england' => 'united kingdom', 'britain' => 'united kingdom', 'great britain' => 'united kingdom',
        'usa' => 'united states', 'us' => 'united states', 'america' => 'united states',
        'türkiye' => 'turkey', 'turkiye' => 'turkey', 'czechia' => 'czech republic', 'holland' => 'netherlands',
    ];

    private function fetchCountryInfo(string $destination): ?array
    {
        $parts = array_map('trim', explode(',', $destination));
        $country = mb_strtolower(trim((string) end($parts)));
        if ($country === '') {
            return null;
        }

        // Resolve: alias → exact key → substring of the full destination.
        $key = self::COUNTRY_ALIASES[$country] ?? $country;
        $info = self::COUNTRY_DATA[$key] ?? null;

        if ($info === null) {
            $haystack = mb_strtolower($destination);
            foreach (self::COUNTRY_DATA as $name => $row) {
                if (str_contains($haystack, $name)) { $info = $row; break; }
            }
            if ($info === null) {
                foreach (self::COUNTRY_ALIASES as $alias => $canonical) {
                    if (str_contains($haystack, $alias)) { $info = self::COUNTRY_DATA[$canonical] ?? null; break; }
                }
            }
        }

        if ($info === null) {
            return null;
        }

        return [
            'name'       => $info['name'],
            'flag_svg'   => 'https://flagcdn.com/' . $info['code'] . '.svg',
            'flag_emoji' => $this->codeToFlagEmoji($info['code']),
            'capital'    => $info['capital'],
            'language'   => $info['language'],
            'currency'   => $info['currency'],
            'timezone'   => $info['timezone'],
        ];
    }

    /** Convert a 2-letter ISO country code to its flag emoji. */
    private function codeToFlagEmoji(string $code): string
    {
        $emoji = '';
        foreach (str_split(strtoupper($code)) as $ch) {
            $emoji .= mb_chr(0x1F1E6 + (ord($ch) - ord('A')), 'UTF-8');
        }
        return $emoji;
    }

    /** @return array<string, mixed> */
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

    /** @param array<string, mixed> $filters */
    private function hasActiveSearchFilters(array $filters): bool
    {
        return !empty($filters['title'])
            || !empty($filters['destination'])
            || !empty($filters['min_price'])
            || !empty($filters['max_price'])
            || !empty($filters['start_date_from'])
            || !empty($filters['start_date_to']);
    }

    /**
     * @param array<int, array<string, mixed>> $entities
     * @return array<int, array<string, mixed>>
     */
    private function filterByEntityId(array $entities, int $voyageId): array
    {
        return array_values(array_filter($entities, fn($e) => (int) $e['voyage_id'] === $voyageId));
    }
}
