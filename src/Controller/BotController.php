<?php

namespace App\Controller;

use App\Controller\Concern\AdminGuardTrait;
use App\Service\AuthService;
use App\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class BotController extends AbstractController
{
    use AdminGuardTrait;

    #[Route('/sendbot', name: 'sendbot', methods: ['POST'])]
    public function sendbot(Request $request, MailerService $mailer, AuthService $authService): JsonResponse
    {
        // Open mail-sender — restrict to admins to prevent it being used as a spam relay.
        if (!$this->isSessionAdmin($request, $authService)) {
            return $this->json(['error' => 'Admin access required.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid or missing email.'], 400);
        }

        try {
            $mailer->sendMailTo($email);
            return $this->json(['message' => 'Email sent to ' . $email]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}