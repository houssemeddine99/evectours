<?php

namespace App\Controller;

use App\Service\WaitlistService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WaitlistController extends AbstractController
{
    public function __construct(
        private readonly WaitlistService $waitlistService,
    ) {}

    #[Route('/voyages/{id}/waitlist/join', name: 'waitlist_join', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function join(Request $request, int $id): Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        if ($this->waitlistService->join($user['id'], $id)) {
            $this->addFlash('success', 'You have been added to the waitlist. We will notify you when a spot opens up.');
        } else {
            $this->addFlash('error', 'Could not join the waitlist. Please try again.');
        }

        return $this->redirectToRoute('travel_voyage_reserve', ['id' => $id]);
    }

    #[Route('/voyages/{id}/waitlist/leave', name: 'waitlist_leave', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function leave(Request $request, int $id): Response
    {
        $user = $request->getSession()->get('auth_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        $this->waitlistService->leave($user['id'], $id);
        $this->addFlash('success', 'You have been removed from the waitlist.');

        return $this->redirectToRoute('travel_voyage_reserve', ['id' => $id]);
    }
}
