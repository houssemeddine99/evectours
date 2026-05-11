<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Entity\Reservation;
use App\Service\ReclamationService;
use App\Service\AiResponseSuggestionService;
use App\Service\ValidationService;
use App\Service\ReservationService;
use App\Message\SendSmsMessage;
use App\Service\RefundRequestService;
use App\Controller\AdminController;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/')]
class ReclamationController extends AbstractController
{
    public function __construct(
        private readonly ReclamationService $reclamationService,
        private readonly ValidationService $validationService,
        private readonly AdminController $adminController,
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationService $reservationService,
        private readonly AiResponseSuggestionService $aiResponseSuggestionService,
        private readonly MessageBusInterface $bus,
        private readonly UserRepository $userRepository,
        private readonly RefundRequestService $refundRequestService,
    ) {}

    // -------------------------------------------------------------------------
    // Auth helpers
    // -------------------------------------------------------------------------

    private function getAuthenticatedUserId(Request $request): ?int
    {
        $user = $request->getSession()->get('auth_user');
        return isset($user['id']) ? (int) $user['id'] : null;
    }

    private function ensureAuthenticated(Request $request): ?Response
    {
        if (null === $this->getAuthenticatedUserId($request)) {
            return $this->redirectToRoute('auth_login');
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // USER ENDPOINTS
    // -------------------------------------------------------------------------

    #[Route('/reclamations', name: 'user_reclamations', methods: ['GET'])]
    public function listUserReclamations(Request $request): Response
    {
        if ($resp = $this->ensureAuthenticated($request)) {
            return $resp;
        }
        $userId = (int) $this->getAuthenticatedUserId($request);
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
        $userId = (int) $this->getAuthenticatedUserId($request);
        $error = null;
        $data = [];

        if ($request->isMethod('GET') && $request->query->has('reservation_id')) {
            $data['reservation_id'] = $request->query->get('reservation_id');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $data['user_id'] = $userId;

            $reservationId = $data['reservation_id'] ?? null;
            if ($reservationId !== null && $reservationId !== '') {
                $reservation = $this->reservationService->getReservationById((int) $reservationId, $userId);
                if ($reservation === null) {
                    $error = ['reservation_id' => ['You can only file a reclamation for a reservation you own.']];
                }
            }

            $this->validationService->validateRequired($data, ['title', 'description']);
            if (!$this->validationService->isValid()) {
                $error = $this->validationService->getErrors();
            }

            if (!isset($error)) {
                $this->reclamationService->createReclamation($data);
                $this->addFlash('success', 'Reclamation submitted successfully.');
                return $this->redirectToRoute('user_reclamations');
            }
        }

        $reservations = $this->reservationService->getReservationsForUser($userId);

        return $this->render('reclamation/form.html.twig', [
            'data'         => $data,
            'errors'       => $error,
            'reservations' => $reservations,
        ]);
    }

    #[Route('/reclamations/{id}', name: 'user_reclamation_detail', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function viewReclamation(Request $request, int $id): Response
    {
        if ($resp = $this->ensureAuthenticated($request)) {
            return $resp;
        }
        $userId = (int) $this->getAuthenticatedUserId($request);
        $reclamation = $this->reclamationService->getReclamationById($id);
        if (!$reclamation || $reclamation->getUserId() !== $userId) {
            throw $this->createNotFoundException('Reclamation not found.');
        }

        $eligibility = $this->reclamationService->evaluateRefundEligibility($id, $userId);
        $refundRequests = $this->refundRequestService->getRequestsByReclamation($id);

        return $this->render('reclamation/detail.html.twig', [
            'reclamation'    => $reclamation,
            'eligibility'    => $eligibility,
            'refundRequests' => $refundRequests,
        ]);
    }

    #[Route('/reclamations/{id}/request-refund', name: 'user_reclamation_request_refund', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function requestRefund(Request $request, int $id): Response
    {
        if ($resp = $this->ensureAuthenticated($request)) {
            return $resp;
        }
        $userId = (int) $this->getAuthenticatedUserId($request);

        $eligibility = $this->reclamationService->evaluateRefundEligibility($id, $userId);
        if (!$eligibility['eligible']) {
            $this->addFlash('error', $eligibility['reason'] ?? 'You are not eligible for a refund.');
            return $this->redirectToRoute('user_reclamation_detail', ['id' => $id]);
        }

        $reclamation = $this->reclamationService->getReclamationById($id);
        if (!$reclamation) {
            throw $this->createNotFoundException('Reclamation not found.');
        }

        $reservation = $this->entityManager->getRepository(Reservation::class)->find($reclamation->getReservationId());
        $amount = $reservation ? $reservation->getTotalPrice() : '0.00';

        $this->refundRequestService->createRefundRequest([
            'reclamation_id' => $id,
            'requester_id'   => $userId,
            'reservation_id' => $reclamation->getReservationId(),
            'amount'         => $amount,
            'reason'         => 'Refund via reclamation #' . $id . ': ' . $reclamation->getTitle(),
        ]);

        $this->addFlash('success', 'Refund request submitted. You will be notified once reviewed.');
        return $this->redirectToRoute('user_reclamation_detail', ['id' => $id]);
    }

    // -------------------------------------------------------------------------
    // ADMIN ENDPOINTS
    // -------------------------------------------------------------------------

    #[Route('/admin/reclamations', name: 'admin_reclamations', methods: ['GET'])]
    public function adminList(Request $request): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }

        $page  = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $email = $request->query->get('email');

        $pagination = $this->reclamationService->getPaginatedReclamations($page, $limit, $email);

        // Enrich with user info so the list shows names instead of raw IDs
        $userIds = array_unique(array_filter(array_map(fn ($r) => $r->getUserId(), $pagination['data'])));
        $userMap = [];
        foreach ($this->userRepository->findBy(['id' => $userIds]) as $u) {
            $userMap[$u->getId()] = $u;
        }

        return $this->render('admin/reclamations/list.html.twig', [
            'reclamations' => $pagination['data'],
            'user_map'     => $userMap,
            'totalItems'   => $pagination['totalItems'],
            'totalPages'   => $pagination['totalPages'],
            'currentPage'  => $pagination['currentPage'],
            'limit'        => $limit,
            'currentEmail' => $email,
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

        $suggestedResponse = null;
        if (!$reclamation->getAdminResponse()) {
            $suggestedResponse = $this->aiResponseSuggestionService->suggestForReclamation($reclamation);
        }

        $refundRequests = $this->refundRequestService->getRequestsByReclamation($id);
        $user = $this->userRepository->find($reclamation->getUserId());

        return $this->render('admin/reclamations/detail.html.twig', [
            'reclamation'       => $reclamation,
            'suggestedResponse' => $suggestedResponse,
            'refundRequests'    => $refundRequests,
            'user'              => $user,
        ]);
    }

    #[Route('/admin/reclamations/{id}/response', name: 'admin_reclamation_response', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function adminAddResponse(Request $request, int $id): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }
        $responseText = (string) $request->request->get('response', '');
        $reclamation  = $this->reclamationService->addResponse($id, $responseText);

        if ($reclamation) {
            $user = $this->userRepository->find($reclamation->getUserId());
            if ($user?->getTel()) {
                $this->bus->dispatch(new SendSmsMessage(
                    $user->getTel(),
                    sprintf('Hello %s, an admin has responded to your reclamation. Log in to read it. – TravelAgency', $user->getUsername())
                ));
            }
        }

        $this->addFlash('success', 'Response added.');
        return $this->redirectToRoute('admin_reclamation_detail', ['id' => $id]);
    }

    #[Route('/admin/reclamations/{id}/status', name: 'admin_reclamation_status', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function adminUpdateStatus(Request $request, int $id): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }
        $status      = (string) $request->request->get('status', 'OPEN');
        $reclamation = $this->reclamationService->updateStatus($id, $status);

        if ($reclamation && in_array($status, ['IN_PROGRESS', 'RESOLVED', 'CLOSED'], true)) {
            $user = $this->userRepository->find($reclamation->getUserId());
            if ($user?->getTel()) {
                $friendly = match (strtoupper($status)) {
                    'IN_PROGRESS' => 'is now being reviewed',
                    'RESOLVED'    => 'has been resolved',
                    default       => 'has been closed',
                };
                $this->bus->dispatch(new SendSmsMessage(
                    $user->getTel(),
                    sprintf('Hello %s, your reclamation %s. Log in to see details. – TravelAgency', $user->getUsername(), $friendly)
                ));
            }
        }

        $this->addFlash('success', 'Status updated.');
        return $this->redirectToRoute('admin_reclamation_detail', ['id' => $id]);
    }

    #[Route('/admin/reclamations/{id}/delete', name: 'admin_reclamation_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function adminDelete(Request $request, int $id): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }
        $entity = $this->entityManager->getRepository(Reclamation::class)->find($id);
        if ($entity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
            $this->addFlash('success', 'Reclamation deleted.');
        }
        return $this->redirectToRoute('admin_reclamations');
    }
}
