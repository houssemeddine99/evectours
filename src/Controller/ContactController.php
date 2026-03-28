<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}