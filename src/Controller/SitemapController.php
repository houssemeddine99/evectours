<?php

namespace App\Controller;

use App\Service\VoyageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    public function __construct(private readonly VoyageService $voyageService) {}

    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        $voyages = $this->voyageService->getAllVoyages();

        $static = [
            ['url' => $this->generateUrl('travel_home',    [], UrlGeneratorInterface::ABSOLUTE_URL), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['url' => $this->generateUrl('travel_voyages', [], UrlGeneratorInterface::ABSOLUTE_URL), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['url' => $this->generateUrl('travel_offers',  [], UrlGeneratorInterface::ABSOLUTE_URL), 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['url' => $this->generateUrl('travel_contact', [], UrlGeneratorInterface::ABSOLUTE_URL), 'priority' => '0.5', 'changefreq' => 'monthly'],
        ];

        $dynamic = [];
        foreach ($voyages as $voyage) {
            if (!empty($voyage['slug'])) {
                $dynamic[] = [
                    'url'        => $this->generateUrl('travel_voyage_detail', ['slug' => $voyage['slug']], UrlGeneratorInterface::ABSOLUTE_URL),
                    'priority'   => '0.8',
                    'changefreq' => 'weekly',
                    'lastmod'    => date('Y-m-d'),
                ];
            }
        }

        $xml = $this->renderView('sitemap.xml.twig', [
            'static'  => $static,
            'dynamic' => $dynamic,
        ]);

        return new Response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
