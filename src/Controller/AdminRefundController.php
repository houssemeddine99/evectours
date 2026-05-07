<?php

namespace App\Controller;

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

        $refunds = $this->entityManager->getRepository(\App\Entity\RefundRequest::class)->findAll();

        return $this->render('admin/refunds/list.html.twig', [
            'refunds' => $refunds,
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

        if ($request->isMethod('POST')) {
            $status          = $request->request->get('status');
            $previousStatus  = strtoupper((string) $refundRequest->getStatus());
            $normalizedStatus = strtoupper(trim((string) $status));

            // Handle partial approved amount
            $approvedAmountRaw = trim((string) $request->request->get('approved_amount', ''));
            if ($approvedAmountRaw !== '' && is_numeric($approvedAmountRaw)) {
                $approvedAmount = number_format((float) $approvedAmountRaw, 2, '.', '');
                $refundRequest->setApprovedAmount($approvedAmount);
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
                    $body = $normalizedStatus === 'APPROVED'
                        ? sprintf('Hello %s, your refund of %.2f TND has been APPROVED. It will be processed in 3-5 days. – TravelAgency', $username, (float) $refundRequest->getAmount())
                        : sprintf('Hello %s, your refund request has been REJECTED. Contact support for more info. – TravelAgency', $username);
                    $this->bus->dispatch(new SendSmsMessage($phone, $body));
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
