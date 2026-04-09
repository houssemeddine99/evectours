<?php

namespace App\Controller;

use App\Service\ActivityService;
use App\Service\ValidationService;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EventController extends AbstractController
{
    public function __construct(
        private readonly ActivityService $activityService,
        private readonly VoyageRepository $voyageRepository,
        private readonly AdminController $adminController,
        private readonly ValidationService $validationService
    ) {}

    // ==================== ADMIN ACTIVITIES (EVENTS) ====================

    #[Route('/admin/activities', name: 'admin_activities', methods: ['GET'])]
    public function adminActivities(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $activities = $this->activityService->getAllActivitiesForAdmin();
        return $this->render('admin/activities.html.twig', [
            'activities' => $activities,
        ]);
    }

    #[Route('/admin/activities/new', name: 'admin_activity_new', methods: ['GET', 'POST'])]
    public function adminNewActivity(Request $request): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // Use ValidationService for validation
            $this->validationService->clearErrors();
            $this->validationService->validateRequired($data, ['voyage_id', 'title', 'date', 'price_per_person']);
            $this->validationService->validateNumber($data['voyage_id'] ?? '', 'voyage_id', 1);
            $this->validationService->validateString($data['title'] ?? '', 'title', 3, 200);
            $this->validationService->validateNumber($data['duration_hours'] ?? '', 'duration_hours', 1, 48);
            $this->validationService->validatePrice($data['price_per_person'] ?? '', 'price_per_person');

            if (!$this->validationService->isValid()) {
                $errors = $this->validationService->getErrors();
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $this->addFlash('error', $error);
                    }
                }
                return $this->render('admin/activity_form.html.twig', [
                    'activity' => $data,
                    'voyages' => $this->voyageRepository->findAll(),
                    'errors' => $errors,
                ]);
            }

            $activity = $this->activityService->createActivity($data);
            if ($activity) {
                $this->addFlash('success', 'Activity created successfully!');
            } else {
                $this->addFlash('error', 'Failed to create activity. Please select a valid voyage.');
            }
            return $this->redirectToRoute('admin_activities');
        }

        return $this->render('admin/activity_form.html.twig', [
            'activity' => null,
            'voyages' => $this->voyageRepository->findAll(),
        ]);
    }

    #[Route('/admin/activities/{id}/edit', name: 'admin_activity_edit', methods: ['GET', 'POST'])]
    public function adminEditActivity(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $activity = $this->activityService->getActivityByIdForAdmin($id);
        if (!$activity) {
            throw $this->createNotFoundException('Activity not found');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $this->activityService->updateActivity($id, $data);
            $this->addFlash('success', 'Activity updated successfully!');
            return $this->redirectToRoute('admin_activities');
        }

        return $this->render('admin/activity_form.html.twig', [
            'activity' => $activity,
            'voyages' => $this->voyageRepository->findAll(),
        ]);
    }

    #[Route('/admin/activities/{id}/delete', name: 'admin_activity_delete', methods: ['GET', 'POST'])]
    public function adminDeleteActivity(Request $request, int $id): Response
    {
        if ($this->adminController->ensureIsAdmin($request) !== null) {
            return $this->adminController->ensureIsAdmin($request);
        }

        $this->activityService->deleteActivity($id);
        $this->addFlash('success', 'Activity deleted successfully!');
        return $this->redirectToRoute('admin_activities');
    }
}
