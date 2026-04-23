<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\UserRepository;
use App\Service\StripePaymentService;
use App\Service\StripeRefundService;
use App\Service\TwilioSmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminRefundController extends AbstractController
{
    public function __construct(
        private readonly AdminController $adminController,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly TwilioSmsService $twilioSmsService,
        private readonly StripeRefundService $stripeRefundService,
        private readonly StripePaymentService $stripePaymentService,
    ) {}

    #[Route('/refunds', name: 'admin_refunds', methods: ['GET'])]
    public function listRefunds(Request $request): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }

        $refunds = $this->entityManager->getRepository(\App\Entity\RefundRequest::class)->findAll();

        return $this->render('admin/refunds/list.html.twig', [
            'refunds' => $refunds,
        ]);
    }

    #[Route('/refunds/{id}/generate-test-reference', name: 'admin_refund_generate_test', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function generateTestReference(Request $request, int $id): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }

        $refundRequest = $this->entityManager->getRepository(\App\Entity\RefundRequest::class)->find($id);
        if (!$refundRequest) {
            return $this->json(['success' => false, 'error' => 'Refund request not found.'], 404);
        }

        $result = $this->stripePaymentService->createAndConfirmTestPayment((string) $refundRequest->getAmount());

        if (!$result['success']) {
            return $this->json(['success' => false, 'error' => $result['error'] ?? 'Stripe test payment failed.']);
        }

        $pi = $result['reference'];

        // Persist on the reservation so the approve flow finds it automatically
        if ($refundRequest->getReservationId() !== null) {
            $reservation = $this->entityManager->getRepository(Reservation::class)->find($refundRequest->getReservationId());
            if ($reservation) {
                $reservation->setPaymentReference($pi);
                $this->entityManager->flush();
            }
        }

        return $this->json(['success' => true, 'reference' => $pi]);
    }

    #[Route('/refunds/{id}', name: 'admin_refund_detail', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function refundDetail(Request $request, int $id): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }

        $refundRequest = $this->entityManager->getRepository(\App\Entity\RefundRequest::class)->find($id);
        if (!$refundRequest) {
            throw $this->createNotFoundException('Refund request not found.');
        }

        if ($request->isMethod('POST')) {
            $status          = $request->request->get('status');
            $previousStatus  = strtoupper((string) $refundRequest->getStatus());
            $normalizedStatus = strtoupper(trim((string) $status));

            if ($normalizedStatus === 'APPROVED' && $normalizedStatus !== $previousStatus) {

                // 1. Try payment reference from the form field
                $paymentReference = trim((string) $request->request->get('payment_reference', ''));

                // 2. Fall back to reference stored on the reservation
                $reservation = null;
                if ($paymentReference === '' && $refundRequest->getReservationId() !== null) {
                    $reservation = $this->entityManager->getRepository(Reservation::class)->find($refundRequest->getReservationId());
                    $paymentReference = trim((string) ($reservation?->getPaymentReference() ?? ''));
                }

                // 3. No reference at all — auto-create a Stripe test payment so we have something to refund
                if ($paymentReference === '') {
                    $autoResult = $this->stripePaymentService->createAndConfirmTestPayment((string) $refundRequest->getAmount());
                    if ($autoResult['success']) {
                        $paymentReference = $autoResult['reference'] ?? '';
                        if ($paymentReference !== '' && $reservation !== null) {
                            $reservation->setPaymentReference($paymentReference);
                        }
                    }
                }

                $stripeResult = $this->stripeRefundService->createRefund(
                    $paymentReference,
                    (string) $refundRequest->getAmount()
                );

                if (!$stripeResult['success']) {
                    $this->addFlash('error', 'Stripe refund failed: ' . ($stripeResult['error'] ?? 'Unknown error'));
                    return $this->redirectToRoute('admin_refund_detail', ['id' => $id]);
                }

                $this->addFlash('success', 'Stripe refund processed. Refund ID: ' . ($stripeResult['refundId'] ?? 'N/A'));
            }

            $refundRequest->setStatus($normalizedStatus);
            $this->entityManager->flush();

            // SMS notification on status change to APPROVED / REJECTED
            $shouldNotify = in_array($normalizedStatus, ['APPROVED', 'REJECTED'], true)
                && $normalizedStatus !== $previousStatus;

            if ($shouldNotify) {
                $requester = $this->userRepository->find($refundRequest->getRequesterId());
                $phone     = $requester?->getTel();
                $username  = $requester?->getUsername() ?? 'Customer';

                if ($phone) {
                    if ($normalizedStatus === 'APPROVED') {
                        $this->twilioSmsService->sendRefundApproved($phone, $username, (float) $refundRequest->getAmount());
                    } else {
                        $this->twilioSmsService->sendRefundRejected($phone, $username);
                    }
                }
            }

            $this->addFlash('success', 'Refund request updated.');
            return $this->redirectToRoute('admin_refund_detail', ['id' => $id]);
        }

        return $this->render('admin/refunds/detail.html.twig', [
            'refund' => $refundRequest,
        ]);
    }
}
