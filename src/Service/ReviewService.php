<?php

namespace App\Service;

use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReviewService
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function submitReview(int $userId, int $voyageId, int $rating, ?string $comment): bool
    {
        $existing = $this->reviewRepository->findByUserAndVoyage($userId, $voyageId);

        if ($existing) {
            $existing->setRating($rating);
            $existing->setComment($comment);
        } else {
            $existing = (new Review())
                ->setUserId($userId)
                ->setVoyageId($voyageId)
                ->setRating($rating)
                ->setComment($comment);
            $this->entityManager->persist($existing);
        }

        $this->entityManager->flush();
        return true;
    }

    /** @return array<int, array{id:int,user_id:int,rating:int,comment:?string,created_at:string}> */
    public function getReviewsForVoyage(int $voyageId): array
    {
        return array_map(
            static fn(Review $r) => [
                'id'         => $r->getId(),
                'user_id'    => $r->getUserId(),
                'rating'     => $r->getRating(),
                'comment'    => $r->getComment(),
                'created_at' => $r->getCreatedAt()->format('Y-m-d'),
            ],
            $this->reviewRepository->findByVoyageId($voyageId)
        );
    }

    public function hasUserReviewed(int $userId, int $voyageId): bool
    {
        return $this->reviewRepository->findByUserAndVoyage($userId, $voyageId) !== null;
    }

    public function getAverageRating(int $voyageId): ?float
    {
        return $this->reviewRepository->getAverageRating($voyageId);
    }

    public function getReviewCount(int $voyageId): int
    {
        return $this->reviewRepository->countByVoyageId($voyageId);
    }

    public function getUserReview(int $userId, int $voyageId): ?array
    {
        $r = $this->reviewRepository->findByUserAndVoyage($userId, $voyageId);
        if (!$r) {
            return null;
        }
        return [
            'id'         => $r->getId(),
            'rating'     => $r->getRating(),
            'comment'    => $r->getComment(),
            'created_at' => $r->getCreatedAt()->format('Y-m-d'),
        ];
    }
}
