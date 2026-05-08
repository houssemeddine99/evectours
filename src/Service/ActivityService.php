<?php

namespace App\Service;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
use App\Repository\VoyageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ActivityService
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly VoyageRepository $voyageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Create a new activity
     * @param array<mixed> $data
     */
    public function createActivity(array $data): ?Activity
    {
        $voyage = $this->voyageRepository->find($data['voyage_id'] ?? 0);
        if (!$voyage) {
            return null;
        }

        $activity = new Activity();
        $activity->setVoyage($voyage);
        $activity->setName($data['name'] ?? '');
        $activity->setDescription($data['description'] ?? null);
        $activity->setDurationHours($data['duration_hours'] ?? null);
        $activity->setPricePerPerson($data['price_per_person'] ?? '0.00');

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }

    /**
     * Update an existing activity
     * @param array<mixed> $data
     */
    public function updateActivity(int $id, array $data): ?Activity
    {
        $activity = $this->activityRepository->find($id);
        if (!$activity) {
            return null;
        }

        if (isset($data['voyage_id'])) {
            $voyage = $this->voyageRepository->find($data['voyage_id']);
            if ($voyage) {
                $activity->setVoyage($voyage);
            }
        }
        if (isset($data['name'])) {
            $activity->setName($data['name']);
        }
        if (isset($data['description'])) {
            $activity->setDescription($data['description']);
        }
        if (isset($data['duration_hours'])) {
            $activity->setDurationHours($data['duration_hours']);
        }
        if (isset($data['price_per_person'])) {
            $activity->setPricePerPerson($data['price_per_person']);
        }

        $this->entityManager->flush();

        return $activity;
    }

    /**
     * Delete an activity
     */
    public function deleteActivity(int $id): bool
    {
        $activity = $this->activityRepository->find($id);
        if (!$activity) {
            return false;
        }

        $this->entityManager->remove($activity);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Get all activities for admin
     * @return array<mixed>
     */
    public function getAllActivitiesForAdmin(): array
    {
        $activities = $this->safeExecute(fn () => $this->activityRepository->findAll(), []);

        return $this->normalizeActivities($activities, true);
    }

    /**
     * Get activity by ID for admin
     * @return array<mixed>
     */
    public function getActivityByIdForAdmin(int $id): ?array
    {
        $activity = $this->safeExecute(fn () => $this->activityRepository->find($id), null);

        if ($activity === null) {
            return null;
        }

        return $this->normalizeActivity($activity, true);
    }

    /**
     * Get activities by voyage ID
     * @return array<mixed>
     */
    public function getActivitiesByVoyageId(int $voyageId): array
    {
        $activities = $this->safeExecute(
            fn () => $this->activityRepository->findBy(['voyage' => $voyageId]),
            []
        );

        return $this->normalizeActivities($activities, false);
    }

    /**
     * Normalize activities for output
     * @param Activity[] $activities
     * @return array
     * @return array<mixed>
     */
    private function normalizeActivities(array $activities, bool $includeVoyageInfo): array
    {
        $normalized = [];
        foreach ($activities as $activity) {
            $normalized[] = $this->normalizeActivity($activity, $includeVoyageInfo);
        }
        return $normalized;
    }

    /**
     * Normalize a single activity for output
     * @return array<mixed>
     */
    private function normalizeActivity(Activity $activity, bool $includeVoyageInfo): array
    {
        $data = [
            'id' => $activity->getId(),
            'name' => $activity->getName(),
            'description' => $activity->getDescription(),
            'duration_hours' => $activity->getDurationHours(),
            'price_per_person' => (float) $activity->getPricePerPerson(),
        ];

        if ($includeVoyageInfo) {
            $voyage = $activity->getVoyage();
            $data['voyage_id'] = $voyage?->getId();
            $data['voyage_title'] = $voyage?->getTitle();
            $data['destination'] = $voyage?->getDestination();
        }

        return $data;
    }

    /**
     * Safely execute a callback with error handling
     * @template T
     * @param callable(): T $callback
     * @param T $default
     * @return T
     */
    private function safeExecute(callable $callback, mixed $default = []): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->logger?->error('ActivityService error', ['error' => $e->getMessage()]);
            return $default;
        }
    }
}