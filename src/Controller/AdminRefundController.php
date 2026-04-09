<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Controller\AdminController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for admin management of refunds.
 */
#[Route('/admin')]
class AdminRefundController extends AbstractController
{
    public function __construct(
        private readonly AdminController $adminController,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /** List all refunded reservations */
    #[Route('/refunds', name: 'admin_refunds', methods: ['GET'])]
    public function listRefunds(Request $request): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }

        // Previously we queried the Reservation entity for a status of 'REFUNDED'.
        // The actual refund data is stored in the RefundRequest entity, so we now
        // fetch all refund requests. Optionally you could filter by status if needed.
        // Fetch all refund requests using the repository. This works with the
        // default Doctrine mapping and returns an array of RefundRequest
        // entities.
        $refunds = $this->entityManager->getRepository(\App\Entity\RefundRequest::class)->findAll();

        return $this->render('admin/refunds/list.html.twig', [
            'refunds' => $refunds,
        ]);
    }

    /** View and edit a single refund */
    #[Route('/refunds/{id}', name: 'admin_refund_detail', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function refundDetail(Request $request, int $id): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }

        // Load the RefundRequest entity instead of a Reservation. The admin view
        // is focused on refund requests, not the underlying reservation record.
        $refundRequest = $this->entityManager->getRepository(\App\Entity\RefundRequest::class)->find($id);
        if (!$refundRequest) {
            throw $this->createNotFoundException('Refund request not found.');
        }

        if ($request->isMethod('POST')) {
            $status = $request->request->get('status');
            if ($status) {
                $refundRequest->setStatus($status);
            }
            // No admin note field exists on RefundRequest; we only allow status updates.
            $this->entityManager->flush();
            $this->addFlash('success', 'Refund request updated.');
            return $this->redirectToRoute('admin_refund_detail', ['id' => $id]);
        }

        return $this->render('admin/refunds/detail.html.twig', [
            'refund' => $refundRequest,
        ]);
    }
}
