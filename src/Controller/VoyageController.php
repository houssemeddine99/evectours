<?php

namespace App\Controller;

use App\Service\VoyageService;
use App\Utility\DatabaseInitializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoyageController extends AbstractController
{
    public function __construct(
        private readonly VoyageService $voyageService,
        private readonly DatabaseInitializer $databaseInitializer
    ) {
    }

    #[Route('/', name: 'travel_home', methods: ['GET'])]
    public function home(): Response
    {
        $this->databaseInitializer->ensureSchema();

        return $this->render('travel/home.html.twig', [
            'active_nav' => 'home',
            'featured_voyages' => $this->voyageService->getFeaturedVoyages(3),
        ]);
    }

    #[Route('/voyages', name: 'travel_voyages', methods: ['GET'])]
    public function voyages(): Response
    {
        return $this->render('travel/voyages.html.twig', [
            'active_nav' => 'voyages',
            'voyages' => $this->voyageService->getAllVoyages(),
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
            'fallback_image' => $this->imageOrFallback(null),
        ]);
    }

    private function imageOrFallback(?string $image): string
    {
        if ($image !== null && trim($image) !== '') {
            return $image;
        }

        return 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=1200&q=80';
    }
}