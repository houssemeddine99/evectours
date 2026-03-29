<?php

namespace App\Controller;

use App\Service\VoyageService;
use App\Service\OfferService;
use App\Utility\DatabaseInitializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoyageController extends AbstractController
{
    public function __construct(
        private readonly VoyageService $voyageService,
        private readonly OfferService $offerService,
        private readonly DatabaseInitializer $databaseInitializer
    ) {
    }

    #[Route('/', name: 'travel_home', methods: ['GET'])]
    public function home(): Response
    {
        $this->databaseInitializer->ensureSchema();

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

        $voyages = $this->voyageService->getVoyages($page, $limit);
        $totalVoyages = $this->voyageService->getTotalVoyages();
        $totalPages = ceil($totalVoyages / $limit) ?: 1;

        return $this->render('travel/voyages.html.twig', [
            'active_nav' => 'voyages',
            'voyages' => $voyages,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/voyages/{id}', name: 'travel_voyage_detail', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function voyageDetail(int $id): Response
    {
        $voyage = $this->voyageService->getVoyageById($id);

        if ($voyage === null) {
            throw $this->createNotFoundException('Voyage not found');
        }

        return $this->render('travel/voyage_detail.html.twig', [
            'active_nav' => 'voyages',
            'voyage' => $voyage,
        ]);
    }

    #[Route('/voyages/{id}/reserve', name: 'travel_voyage_reserve', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function voyageReserve(int $id): Response
    {
        $voyage = $this->voyageService->getVoyageById($id);

        if ($voyage === null) {
            throw $this->createNotFoundException('Voyage not found');
        }

        return $this->render('travel/reserve.html.twig', [
            'voyage' => $voyage,
        ]);
    }

    #[Route('/offers', name: 'travel_offers', methods: ['GET'])]
    public function offers(): Response
    {
        $offers = $this->offerService->getActiveOffers();

        return $this->render('travel/offers.html.twig', [
            'active_nav' => 'offers',
            'offers' => $offers,
        ]);
    }

    #[Route('/bookings', name: 'travel_bookings', methods: ['GET'])]
    public function bookings(): Response
    {
        return $this->render('travel/bookings.html.twig', [
            'active_nav' => 'bookings',
        ]);
    }

    #[Route('/favorites', name: 'travel_favorites', methods: ['GET'])]
    public function favorites(): Response
    {
        return $this->render('travel/favorites.html.twig', [
            'active_nav' => 'favorites',
        ]);
    }

    #[Route('/contact', name: 'travel_contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('travel/contact.html.twig', [
            'active_nav' => 'contact',
        ]);
    }

    #[Route('/favicon.ico', name: 'travel_favicon', methods: ['GET'])]
    public function favicon(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}