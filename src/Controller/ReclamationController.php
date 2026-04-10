<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Service\ReclamationService;
use App\Service\ValidationService;
use App\Service\ReservationService;
use App\Controller\AdminController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller handling user and admin interactions with Reclamations.
 *
 * All public routes require the user to be authenticated (checked via session).
 * Admin routes reuse AdminController::ensureIsAdmin to enforce admin access.
 */
#[Route('/')]
class ReclamationController extends AbstractController
{
    public function __construct(
        private readonly ReclamationService $reclamationService,
    private readonly ValidationService $validationService,
    private readonly AdminController $adminController,
    private readonly EntityManagerInterface $entityManager,
    private readonly ReservationService $reservationService,
    ) {}

    // ---------------------------------------------------------------------
    // Helper methods for authentication / authorization
    // ---------------------------------------------------------------------
    private function getAuthenticatedUserId(Request $request): ?int
    {
        $user = $request->getSession()->get('auth_user');
        return $user['id'] ?? null;
    }

    private function ensureAuthenticated(Request $request): ?Response
    {
        if (null === $this->getAuthenticatedUserId($request)) {
            return $this->redirectToRoute('auth_login');
        }
        return null;
    }

    // ---------------------------------------------------------------------
    // USER ENDPOINTS
    // ---------------------------------------------------------------------
    #[Route('/reclamations', name: 'user_reclamations', methods: ['GET'])]
    public function listUserReclamations(Request $request): Response
    {
        if ($resp = $this->ensureAuthenticated($request)) {
            return $resp;
        }
        $userId = $this->getAuthenticatedUserId($request);
        $reclamations = $this->reclamationService->getReclamationsByUser($userId);
        return $this->render('reclamation/list.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    #[Route('/reclamations/new', name: 'user_reclamation_new', methods: ['GET', 'POST'])]
    public function createReclamation(Request $request): Response
    {
        if ($resp = $this->ensureAuthenticated($request)) {
            return $resp;
        }
        $userId = $this->getAuthenticatedUserId($request);
        $error = null;
        $data = [];
        // Pre‑fill reservation_id from query string for convenience (e.g., from reservation detail page)
        if ($request->isMethod('GET') && $request->query->has('reservation_id')) {
            $data['reservation_id'] = $request->query->get('reservation_id');
        }
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            // Ensure the logged‑in user id is used, ignore any submitted value
            $data['user_id'] = $userId;

            // Verify the reservation belongs to the user (if provided)
            $reservationId = $data['reservation_id'] ?? null;
            if ($reservationId !== null) {
                $reservation = $this->reservationService->getReservationById((int) $reservationId, $userId);
                if ($reservation === null) {
                    $error = ['reservation_id' => ['You can only file a reclamation for a reservation you own.']];
                }
            }

            // Validate required fields (title and description are mandatory)
            $this->validationService->validateRequired($data, ['title', 'description']);
            if (!$this->validationService->isValid()) {
                $error = $this->validationService->getErrors();
            }

            // If no errors so far, attempt to create the reclamation
            if (!isset($error)) {
                $reclamation = $this->reclamationService->createReclamation($data);
                if ($reclamation) {
                    $this->addFlash('success', 'Reclamation submitted successfully.');
                    return $this->redirectToRoute('user_reclamations');
                }
                $error = ['general' => ['Unable to create reclamation.']];
            }
        }
        return $this->render('reclamation/form.html.twig', [
            'data' => $data,
            'errors' => $error,
        ]);
    }

    #[Route('/reclamations/{id}', name: 'user_reclamation_detail', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function viewReclamation(Request $request, int $id): Response
    {
        if ($resp = $this->ensureAuthenticated($request)) {
            return $resp;
        }
        $userId = $this->getAuthenticatedUserId($request);
        $reclamation = $this->reclamationService->getReclamationById($id);
        if (!$reclamation || $reclamation->getUserId() !== $userId) {
            // Prevent IDOR – either not found or not owned by the user
            throw $this->createNotFoundException('Reclamation not found.');
        }
        return $this->render('reclamation/detail.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    // ---------------------------------------------------------------------
    // ADMIN ENDPOINTS
    // ---------------------------------------------------------------------
    #[Route('/admin/reclamations', name: 'admin_reclamations', methods: ['GET'])]
    public function adminList(Request $request): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }
        $reclamations = $this->reclamationService->getOpenReclamations(); // could be all, adjust as needed
        return $this->render('admin/reclamations/list.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    #[Route('/admin/reclamations/{id}', name: 'admin_reclamation_detail', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function adminDetail(Request $request, int $id): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }
        $reclamation = $this->reclamationService->getReclamationById($id);
        if (!$reclamation) {
            throw $this->createNotFoundException('Reclamation not found.');
        }
        return $this->render('admin/reclamations/detail.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/admin/reclamations/{id}/response', name: 'admin_reclamation_response', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function adminAddResponse(Request $request, int $id): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }
        $response = $request->request->get('response', '');
        $this->reclamationService->addResponse($id, $response);
        $this->addFlash('success', 'Response added.');
        return $this->redirectToRoute('admin_reclamation_detail', ['id' => $id]);
    }

    #[Route('/admin/reclamations/{id}/status', name: 'admin_reclamation_status', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function adminUpdateStatus(Request $request, int $id): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }
        $status = $request->request->get('status', 'OPEN');
        $this->reclamationService->updateStatus($id, $status);
        $this->addFlash('success', 'Status updated.');
        return $this->redirectToRoute('admin_reclamation_detail', ['id' => $id]);
    }

    #[Route('/admin/reclamations/{id}/delete', name: 'admin_reclamation_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function adminDelete(Request $request, int $id): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }
        // Deleting via EntityManager injected service
        $entity = $this->entityManager->getRepository(Reclamation::class)->find($id);
        if ($entity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
            $this->addFlash('success', 'Reclamation deleted.');
        }
        return $this->redirectToRoute('admin_reclamations');
    }
}
