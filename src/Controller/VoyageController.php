<?php

namespace App\Controller;

use App\Service\VoyageService;
use App\Service\OfferService;
use App\Service\ActivityService;
use App\Service\VoyageImageService;
use App\Service\ValidationService;
use App\Service\SearchHistoryService;
use App\Service\VoyageVisitService;
use App\Repository\VoyageRepository;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

        // Get search and filter parameters
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

        // Build search filters
        if (!empty($search)) {
            $filters['destination'] = $search;
            $filters['title'] = $search;
        }
        if (!empty($minPrice)) {
            $filters['min_price'] = $minPrice;
        }
        if (!empty($maxPrice)) {
            $filters['max_price'] = $maxPrice;
        }

        // Use search if filters are applied
        if (!empty($search) || !empty($minPrice) || !empty($maxPrice)) {
            $this->logger?->info('Public searching voyages', $filters);
            $voyages = $this->voyageService->searchVoyages($filters);
            $totalVoyages = $this->voyageService->countSearchResults($filters);
        } else {
            $voyages = $this->voyageService->getVoyages($page, $limit);
            $totalVoyages = $this->voyageService->getTotalVoyages();
        }

        // Record search history for public searches (only when a search term is provided)
        if (!empty($search)) {
            $sessionUser = $request->getSession()->get('auth_user');
            $userId = $sessionUser['id'] ?? 0;
            $resultsFound = is_array($voyages) ? count($voyages) : 0;
            $this->searchHistoryService->recordSearch($userId, $search, 'voyage', $resultsFound);
        }

        $totalPages = ceil($totalVoyages / $limit) ?: 1;

        return $this->render('travel/voyages.html.twig', [
            'active_nav' => 'voyages',
            'voyages' => $voyages,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'search' => $search,
            'filters' => $filters,
        ]);
    }

    #[Route('/voyages/{id}', name: 'travel_voyage_detail', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function voyageDetail(Request $request, int $id): Response
    {
        $voyage = $this->voyageService->getVoyageById($id);

        if ($voyage === null) {
            throw $this->createNotFoundException('Voyage not found');
        }

        // Get user from session
        $sessionUser = $request->getSession()->get('auth_user');

        // Use session ID if available, otherwise default to 1
        $userId = ($sessionUser && isset($sessionUser['id'])) ? (int)$sessionUser['id'] : 1;

        // Record the visit for every guest or logged-in user
        $this->voyageVisitService->recordVisit($userId, $id, 'detail');

        $offers = $this->offerService->getActiveOffers();
        $offerForVoyage = array_filter($offers, fn($o) => (int) $o['voyage_id'] === $id);
        $offer = $offerForVoyage ? array_values($offerForVoyage)[0] : null;

        return $this->render('travel/voyage_detail.html.twig', [
            'active_nav' => 'voyages',
            'voyage' => $voyage,
            'offer' => $offer,
        ]);
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
            $voyage = $this->voyageService->createVoyage($data);
            if ($voyage) {
                $this->addFlash('success', 'Voyage created successfully!');
            } else {
                $this->addFlash('error', 'Failed to create voyage.');
            }
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

    // ==================== HELPER METHODS ====================

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
