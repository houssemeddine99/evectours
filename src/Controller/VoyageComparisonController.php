<?php

namespace App\Controller;

use App\Service\VoyageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoyageComparisonController extends AbstractController
{
    private const MAX_COMPARE = 3;

    public function __construct(
        private readonly VoyageService $voyageService,
    ) {}

    #[Route('/voyages/{id}/compare-pick', name: 'compare_pick', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function pick(Request $request, int $id): Response
    {
        $anchor = $this->voyageService->getVoyageById($id);
        if ($anchor === null) {
            throw $this->createNotFoundException('Voyage not found');
        }

        $all = $this->voyageService->getAllActiveVoyages();
        $others = array_values(array_filter($all, fn($v) => (int) $v['id'] !== $id));

        return $this->render('travel/compare_pick.html.twig', [
            'active_nav' => 'voyages',
            'anchor'     => $anchor,
            'others'     => $others,
        ]);
    }

    #[Route('/compare/instant/{a}/{b}', name: 'compare_instant', requirements: ['a' => '\\d+', 'b' => '\\d+'], methods: ['GET'])]
    public function instant(Request $request, int $a, int $b): Response
    {
        $request->getSession()->set('compare_list', [$a, $b]);
        return $this->redirectToRoute('compare_show');
    }

    #[Route('/compare/add/{id}', name: 'compare_add', methods: ['POST'])]
    public function add(Request $request, int $id): JsonResponse
    {
        $session = $request->getSession();
        $list = $session->get('compare_list', []);

        if (in_array($id, $list, true)) {
            return $this->json(['status' => 'already_added', 'list' => $list]);
        }

        if (count($list) >= self::MAX_COMPARE) {
            return $this->json(['status' => 'max_reached', 'list' => $list], 400);
        }

        $list[] = $id;
        $session->set('compare_list', $list);

        return $this->json(['status' => 'added', 'list' => $list]);
    }

    #[Route('/compare/remove/{id}', name: 'compare_remove', methods: ['POST'])]
    public function remove(Request $request, int $id): JsonResponse
    {
        $session = $request->getSession();
        $list = array_values(array_filter(
            $session->get('compare_list', []),
            fn($v) => $v !== $id
        ));
        $session->set('compare_list', $list);

        return $this->json(['status' => 'removed', 'list' => $list]);
    }

    #[Route('/compare/clear', name: 'compare_clear', methods: ['POST'])]
    public function clear(Request $request): RedirectResponse
    {
        $request->getSession()->remove('compare_list');
        return $this->redirectToRoute('travel_voyages');
    }

    #[Route('/compare', name: 'compare_show', methods: ['GET'])]
    public function show(Request $request): Response
    {
        $list = $request->getSession()->get('compare_list', []);
        $voyages = [];

        foreach ($list as $id) {
            $voyage = $this->voyageService->getVoyageById((int) $id);
            if ($voyage !== null) {
                $voyages[] = $voyage;
            }
        }

        return $this->render('travel/voyage_comparison.html.twig', [
            'active_nav' => 'voyages',
            'voyages' => $voyages,
        ]);
    }
}
