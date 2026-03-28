<?php

namespace App\Controller;

use App\Service\VoyageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    public function __construct(
        private readonly VoyageService $voyageService
    ) {
    }

    #[Route('/account/favorites', name: 'account_favorites', methods: ['GET'])]
    public function accountFavorites(Request $request): Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        // Simulated favorites for now
        $voyages = $this->voyageService->getFeaturedVoyages(3);

        return $this->render('travel/favorites.html.twig', [
            'active_nav' => 'account',
            'favorites' => $voyages,
        ]);
    }
}