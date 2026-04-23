<?php

namespace App\Controller;

use App\Service\FavoriteService;
use App\Service\VoyageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FavoriteController extends AbstractController
{
    public function __construct(
        private readonly FavoriteService $favoriteService,
        private readonly VoyageService $voyageService,
    ) {}

    #[Route('/favorites', name: 'travel_favorites', methods: ['GET'])]
    public function favorites(Request $request): Response
    {
        $sessionUser = $request->getSession()->get('auth_user');
        $userId = ($sessionUser && isset($sessionUser['id'])) ? (int) $sessionUser['id'] : 0;

        $voyages = [];
        if ($userId > 0) {
            $voyageIds = $this->favoriteService->getFavoriteVoyageIds($userId);
            foreach ($voyageIds as $voyageId) {
                $voyage = $this->voyageService->getVoyageById($voyageId);
                if ($voyage !== null) {
                    $voyages[] = $voyage;
                }
            }
        }

        return $this->render('travel/favorites.html.twig', [
            'active_nav' => 'favorites',
            'favorites' => $voyages,
        ]);
    }

    #[Route('/favorites/toggle/{voyageId}', name: 'favorite_toggle', methods: ['POST'])]
    public function toggle(Request $request, int $voyageId): JsonResponse
    {
        $sessionUser = $request->getSession()->get('auth_user');
        $userId = ($sessionUser && isset($sessionUser['id'])) ? (int) $sessionUser['id'] : 0;

        if ($userId === 0) {
            return $this->json(['error' => 'Login required'], 401);
        }

        $added = $this->favoriteService->toggleFavorite($userId, $voyageId);

        return $this->json(['added' => $added, 'voyageId' => $voyageId]);
    }
}
