<?php

namespace App\Controller;

use App\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'travel_contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('travel/contact.html.twig', [
            'active_nav' => 'contact',
        ]);
    }

    #[Route('/contact/send', name: 'contact_send', methods: ['POST'])]
    public function send(Request $request, MailerService $mailer): JsonResponse
    {
        $data = [
            'full_name'    => trim($request->request->get('full_name', '')),
            'email'        => trim($request->request->get('email', '')),
            'phone'        => trim($request->request->get('phone', '')),
            'destination'  => trim($request->request->get('destination', '')),
            'travel_type'  => trim($request->request->get('travel_type', '')),
            'duration'     => trim($request->request->get('duration', '')),
            'budget'       => trim($request->request->get('budget', '')),
            'travel_dates' => trim($request->request->get('travel_dates', '')),
            'message'      => trim($request->request->get('message', '')),
        ];

        if (empty($data['full_name']) || empty($data['email']) || empty($data['destination'])) {
            return $this->json(['success' => false, 'error' => 'Please fill in all required fields.'], 422);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['success' => false, 'error' => 'Please enter a valid email address.'], 422);
        }

        try {
            $mailer->sendContactForm($data);
            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => 'Failed to send. Please try again later.'], 500);
        }
    }

    #[Route('/favicon.ico', name: 'travel_favicon', methods: ['GET'])]
    public function favicon(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}