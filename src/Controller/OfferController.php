<?php

namespace App\Controller;

use App\Service\OfferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OfferController extends AbstractController
{
    public function __construct(
        private readonly OfferService $offerService
    ) {
    }

    #[Route('/offers', name: 'travel_offers', methods: ['GET'])]
    public function offers(): Response
    {
        return $this->render('travel/offers.html.twig', [
            'active_nav' => 'offers',
            'offers' => $this->offerService->getActiveOffers(),
        ]);
    }
}