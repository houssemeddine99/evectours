<?php

namespace App\Service;

use App\Entity\Favorite;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;

class FavoriteService
{
    public function __construct(
        private readonly FavoriteRepository $favoriteRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function toggleFavorite(int $userId, int $voyageId): bool
    {
        $existing = $this->favoriteRepository->findByUserAndVoyage($userId, $voyageId);

        if ($existing) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
            return false;
        }

        $favorite = new Favorite();
        $favorite->setUserId($userId);
        $favorite->setVoyageId($voyageId);
        $favorite->setCreatedAt(new \DateTime());
        $this->entityManager->persist($favorite);
        $this->entityManager->flush();
        return true;
    }

    public function isFavorite(int $userId, int $voyageId): bool
    {
        return $this->favoriteRepository->findByUserAndVoyage($userId, $voyageId) !== null;
    }

    /** @return int[] */
    public function getFavoriteVoyageIds(int $userId): array
    {
        return $this->favoriteRepository->findVoyageIdsByUser($userId);
    }
}
