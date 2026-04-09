<?php

namespace App\Controller;

use App\Service\OfferService;
use App\Service\ValidationService;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OfferController extends AbstractController
{
    public function __construct(
        private readonly OfferService $offerService,
        private readonly VoyageRepository $voyageRepository,
        private readonly AdminController $adminController,
        private readonly ValidationService $validationService
    ) {}
    // ==================== uSER CAN ONLY SEE OFFERS  ====================
    #[Route('/offers', name: 'travel_offers', methods: ['GET'])]
    public function offers(): Response
    {
        return $this->render('travel/offers.html.twig', [
            'active_nav' => 'offers',
            'offers' => $this->offerService->getActiveOffers(),
        ]);
    }

    // ==================== ADMIN OFFERS ====================

    #[Route('/admin/offers', name: 'admin_offers', methods: ['GET'])]
    public function adminOffers(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $offers = $this->offerService->getAllOffersForAdmin();
        return $this->render('admin/offers.html.twig', [
            'offers' => $offers,
        ]);
    }

    #[Route('/admin/offers/new', name: 'admin_offer_new', methods: ['GET', 'POST'])]
    public function adminNewOffer(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $data['is_active'] = $request->request->get('is_active', '1') === '1';

            // Use ValidationService for validation
            $this->validationService->clearErrors();
            $this->validationService->validateRequired($data, ['voyage_id', 'discount_percentage', 'start_date', 'end_date']);
            $this->validationService->validateNumber($data['voyage_id'] ?? '', 'voyage_id', 1);
            $this->validationService->validateNumber($data['discount_percentage'] ?? '', 'discount_percentage', 1, 100);
            $this->validationService->validateDate($data['start_date'] ?? '', 'start_date');
            $this->validationService->validateDate($data['end_date'] ?? '', 'end_date');
            $this->validationService->validateDateRange($data['start_date'] ?? '', $data['end_date'] ?? '');

            if (!$this->validationService->isValid()) {
                $errors = $this->validationService->getErrors();
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $this->addFlash('error', $error);
                    }
                }
                return $this->render('admin/offer_form.html.twig', [
                    'offer' => $data,
                    'voyages' => $this->voyageRepository->findAll(),
                    'errors' => $errors,
                ]);
            }

            $offer = $this->offerService->createOffer($data);
            if ($offer) {
                $this->addFlash('success', 'Offer created successfully!');
            } else {
                $this->addFlash('error', 'Failed to create offer. Please select a valid voyage.');
            }
            return $this->redirectToRoute('admin_offers');
        }

        return $this->render('admin/offer_form.html.twig', [
            'offer' => null,
            'voyages' => $this->voyageRepository->findAll(),
        ]);
    }

    #[Route('/admin/offers/{id}/edit', name: 'admin_offer_edit', methods: ['GET', 'POST'])]
    public function adminEditOffer(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $offer = $this->offerService->getOfferByIdForAdmin($id);
        if (!$offer) {
            throw $this->createNotFoundException('Offer not found');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $data['is_active'] = $request->request->get('is_active', '1') === '1';

            $this->offerService->updateOffer($id, $data);
            $this->addFlash('success', 'Offer updated successfully!');
            return $this->redirectToRoute('admin_offers');
        }

        return $this->render('admin/offer_form.html.twig', [
            'offer' => $offer,
            'voyages' => $this->voyageRepository->findAll(),
        ]);
    }

    #[Route('/admin/offers/{id}/delete', name: 'admin_offer_delete', methods: ['GET', 'POST'])]
    public function adminDeleteOffer(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $this->offerService->deleteOffer($id);
        $this->addFlash('success', 'Offer deleted successfully!');
        return $this->redirectToRoute('admin_offers');
    }
}
