<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\UserRepository;
use App\Message\SendSmsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
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
        private readonly MessageBusInterface $bus,
    ) {}

    #[Route('/refunds', name: 'admin_refunds', methods: ['GET'])]
    public function listRefunds(Request $request): Response
    {
        if ($adminResp = $this->adminController->ensureIsAdmin($request)) {
            return $adminResp;
        }

        $refunds = $this->entityManager->getRepository(\App\Entity\RefundRequest::class)
            ->findBy([], ['createdAt' => 'DESC']);

        // Enrich with user info
        $userIds = array_unique(array_filter(array_map(fn ($r) => $r->getRequesterId(), $refunds)));
        $userMap = [];
        foreach ($this->userRepository->findBy(['id' => $userIds]) as $u) {
            $userMap[$u->getId()] = $u;
        }

        return $this->render('admin/refunds/list.html.twig', [
            'refunds'  => $refunds,
            'user_map' => $userMap,
        ]);
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

        $requester   = $this->userRepository->find($refundRequest->getRequesterId());
        $reservation = $refundRequest->getReservationId()
            ? $this->entityManager->getRepository(Reservation::class)->find($refundRequest->getReservationId())
            : null;

        if ($request->isMethod('POST')) {
            $status           = $request->request->get('status');
            $previousStatus   = strtoupper((string) $refundRequest->getStatus());
            $normalizedStatus = strtoupper(trim((string) $status));

            // Handle partial approved amount
            $approvedAmountRaw = trim((string) $request->request->get('approved_amount', ''));
            if ($approvedAmountRaw !== '' && is_numeric($approvedAmountRaw)) {
                $refundRequest->setApprovedAmount(number_format((float) $approvedAmountRaw, 2, '.', ''));
            }

            $refundRequest->setStatus($normalizedStatus);

            // When approved: mark reservation as CANCELLED + payment REFUNDED
            if ($normalizedStatus === 'APPROVED' && $previousStatus !== 'APPROVED' && $reservation) {
                $reservation->setStatus('CANCELLED');
                $reservation->setPaymentStatus('REFUNDED');
                $reservation->setUpdatedAt(new \DateTime());
            }

            $this->entityManager->flush();

            // SMS notification
            if (in_array($normalizedStatus, ['APPROVED', 'REJECTED'], true) && $normalizedStatus !== $previousStatus) {
                $phone    = $requester?->getTel();
                $username = $requester?->getUsername() ?? 'Customer';
                if ($phone) {
                    $effectiveAmount = $refundRequest->getEffectiveAmount();
                    $body = $normalizedStatus === 'APPROVED'
                        ? sprintf('Hello %s, your refund of %.2f TND has been APPROVED and will be processed in 3-5 business days. – Evec Tours', $username, (float) $effectiveAmount)
                        : sprintf('Hello %s, your refund request has been REJECTED. Please contact support for more information. – Evec Tours', $username);
                    $this->bus->dispatch(new SendSmsMessage($phone, $body));
                }
            }

            $this->addFlash('success', 'Refund request updated successfully.');
            return $this->redirectToRoute('admin_refund_detail', ['id' => $id]);
        }

        return $this->render('admin/refunds/detail.html.twig', [
            'refund'      => $refundRequest,
            'requester'   => $requester,
            'reservation' => $reservation,
        ]);
    }
}
