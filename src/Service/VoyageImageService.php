<?php

namespace App\Service;

use App\Entity\VoyageImage;
use App\Repository\VoyageImageRepository;
use App\Repository\VoyageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class VoyageImageService
{
    public function __construct(
        private readonly VoyageImageRepository $voyageImageRepository,
        private readonly VoyageRepository $voyageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Create a new voyage image
     */
public function createVoyageImage(array $data): ?VoyageImage
{
    $voyage = $this->voyageRepository->find($data['voyage_id'] ?? 0);
    if (!$voyage) {
        return null;
    }

    $image = new VoyageImage();
    // DO NOT set ID - let Doctrine auto-generate it
    $image->setVoyageId($voyage->getId());
    $image->setImageUrl($data['image_url'] ?? '');
    $image->setCloudinaryPublicId($data['cloudinary_public_id'] ?? '');
    $image->setCreatedAt(new \DateTime());
    $image->setUpdatedAt(new \DateTime());

    $this->entityManager->persist($image);
    $this->entityManager->flush();

    return $image;
}

    /**
     * Update an existing voyage image
     */
    public function updateVoyageImage(int $id, array $data): ?VoyageImage
    {
        $image = $this->voyageImageRepository->find($id);
        if (!$image) {
            return null;
        }

        if (isset($data['voyage_id'])) {
            $image->setVoyageId($data['voyage_id']);
        }
        if (isset($data['image_url'])) {
            $image->setImageUrl($data['image_url']);
        }
        if (isset($data['cloudinary_public_id'])) {
            $image->setCloudinaryPublicId($data['cloudinary_public_id']);
        }

        $image->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return $image;
    }

    /**
     * Delete a voyage image
     */
    public function deleteVoyageImage(int $id): bool
    {
        $image = $this->voyageImageRepository->find($id);
        if (!$image) {
            return false;
        }

        $this->entityManager->remove($image);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Get all images for admin
     */
    public function getAllImagesForAdmin(): array
    {
        $images = $this->safeExecute(fn () => $this->voyageImageRepository->findAll(), []);

        return $this->normalizeImages($images, true);
    }

    /**
     * Get image by ID for admin
     */
    public function getImageByIdForAdmin(int $id): ?array
    {
        $image = $this->safeExecute(fn () => $this->voyageImageRepository->find($id));

        if ($image === null) {
            return null;
        }

        return $this->normalizeImage($image, true);
    }

    /**
     * Get images by voyage ID
     */
    public function getImagesByVoyageId(int $voyageId): array
    {
        $images = $this->safeExecute(
            fn () => $this->voyageImageRepository->findByVoyageId($voyageId),
            []
        );

        return array_map(function ($image) {
            if (is_array($image)) {
                return $image;
            }
            return [
                'id' => $image->getId(),
                'image_url' => $image->getImageUrl(),
                'cloudinary_public_id' => $image->getCloudinaryPublicId(),
            ];
        }, $images);
    }

    /**
     * Normalize images for output
     * @param VoyageImage[] $images
     * @return array
     */
    private function normalizeImages(array $images, bool $includeVoyageInfo): array
    {
        $normalized = [];
        foreach ($images as $image) {
            $normalized[] = $this->normalizeImage($image, $includeVoyageInfo);
        }
        return $normalized;
    }

    /**
     * Normalize a single image for output
     */
    private function normalizeImage(VoyageImage $image, bool $includeVoyageInfo): array
    {
        $data = [
            'id' => $image->getId(),
            'voyage_id' => $image->getVoyageId(),
            'image_url' => $image->getImageUrl(),
            'cloudinary_public_id' => $image->getCloudinaryPublicId(),
            'created_at' => $image->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $image->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        if ($includeVoyageInfo) {
            $voyage = $this->safeExecute(fn () => $this->voyageRepository->find($image->getVoyageId()));
            $data['voyage_title'] = $voyage?->getTitle() ?? 'Unknown';
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
            $this->logger?->error('VoyageImageService error', ['error' => $e->getMessage()]);
            return $default;
        }
    }
}