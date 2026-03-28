<?php

namespace App\Controller;

use App\Service\VoyageService;
use App\Service\OfferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TravelController extends AbstractController
{
    public function __construct(
        private VoyageService $voyageService,
        private OfferService $offerService,
    ) {}

    #[Route('/', name: 'travel_home', methods: ['GET'])]
    public function home(): Response
    {
        $featuredVoyages = $this->voyageService->getFeaturedVoyages(6);

        return $this->render('travel/home.html.twig', [
            'featured_voyages' => $featuredVoyages,
        ]);
    }

    #[Route('/voyages', name: 'travel_voyages', methods: ['GET'])]
    public function voyages(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 12;

        $voyages = $this->voyageService->getVoyages($page, $limit);
        $totalVoyages = $this->voyageService->getTotalVoyages();
        $totalPages = ceil($totalVoyages / $limit);

        return $this->render('travel/voyages.html.twig', [
            'voyages' => $voyages,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/voyages/{id}', name: 'travel_voyage_detail', methods: ['GET'])]
    public function voyageDetail(int $id): Response
    {
        $voyage = $this->voyageService->getVoyageById($id);

        if (!$voyage) {
            throw $this->createNotFoundException('Voyage not found');
        }

        return $this->render('travel/voyage_detail.html.twig', [
            'voyage' => $voyage,
        ]);
    }

    #[Route('/offers', name: 'travel_offers', methods: ['GET'])]
    public function offers(): Response
    {
        $offers = $this->offerService->getActiveOffers();

        return $this->render('travel/offers.html.twig', [
            'offers' => $offers,
        ]);
    }

    #[Route('/bookings', name: 'travel_bookings', methods: ['GET'])]
    public function bookings(): Response
    {
        // TODO: Implement bookings logic
        return $this->render('travel/bookings.html.twig');
    }

    #[Route('/favorites', name: 'travel_favorites', methods: ['GET'])]
    public function favorites(): Response
    {
        // TODO: Implement favorites logic
        return $this->render('travel/favorites.html.twig');
    }

    #[Route('/contact', name: 'travel_contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('travel/contact.html.twig');
    }

    #[Route('/favicon.ico', name: 'travel_favicon', methods: ['GET'])]
    public function favicon(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
