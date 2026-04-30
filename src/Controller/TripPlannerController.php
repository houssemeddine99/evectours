<?php

namespace App\Controller;

use App\Service\AiBudgetPlannerService;
use App\Service\OfferService;
use App\Service\VoyageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TripPlannerController extends AbstractController
{
    public function __construct(
        private readonly AiBudgetPlannerService $aiPlannerService,
        private readonly VoyageService $voyageService,
        private readonly OfferService $offerService,
    ) {}

    #[Route('/trip-planner', name: 'trip_planner', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('travel_home');
    }

    #[Route('/trip-planner/plan', name: 'trip_planner_plan', methods: ['POST'])]
    public function plan(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $now     = time();
        $window  = (int) ($session->get('ai_plan_window', 0));
        $count   = (int) ($session->get('ai_plan_count', 0));
        if ($now - $window < 60) {
            if ($count >= 5) {
                return $this->json(['success' => false, 'error' => 'Too many requests. Please wait a minute.'], 429);
            }
            $session->set('ai_plan_count', $count + 1);
        } else {
            $session->set('ai_plan_window', $now);
            $session->set('ai_plan_count', 1);
        }

        $userInput = trim((string) $request->request->get('query', ''));
        if ($userInput === '') {
            return $this->json(['success' => false, 'error' => 'Please describe your trip.']);
        }

        $voyages = $this->voyageService->getSlimVoyagesForAi(50);
        $offers  = $this->offerService->getActiveOffers();

        $result = $this->aiPlannerService->plan($userInput, $voyages, $offers);
        if ($result === null) {
            return $this->json(['success' => false, 'error' => 'AI service unavailable. Please try again.']);
        }

        // Enrich recommendations — slim voyages already have the fields we need
        $voyageMap = array_column($voyages, null, 'id');
        $offerMap  = array_column($offers, null, 'id');

        $enriched = [];
        foreach ($result['recommendations'] as $rec) {
            $vid    = (int) ($rec['voyage_id'] ?? 0);
            $oid    = isset($rec['offer_id']) ? (int) $rec['offer_id'] : null;
            $voyage = $voyageMap[$vid] ?? null;
            if (!$voyage) continue;

            $enriched[] = [
                'voyage'          => $voyage,
                'offer'           => ($oid && isset($offerMap[$oid])) ? $offerMap[$oid] : null,
                'estimated_price' => $rec['estimated_price'] ?? null,
                'reason'          => $rec['reason'] ?? '',
            ];
        }

        return $this->json([
            'success'         => true,
            'recommendations' => $enriched,
            'explanation'     => $result['explanation'],
        ]);
    }
}
