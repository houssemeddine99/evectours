<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Review;
use App\Repository\ReviewRepository;
use App\Service\ReviewService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ReviewServiceTest extends TestCase
{
    private function makeReview(int $id, int $userId, int $voyageId, int $rating, ?string $comment): Review
    {
        $r = new Review();
        $r->setUserId($userId)
          ->setVoyageId($voyageId)
          ->setRating($rating)
          ->setComment($comment);
        return $r;
    }

    public function testSubmitNewReviewPersistsAndFlushes(): void
    {
        $repo = $this->createMock(ReviewRepository::class);
        $repo->method('findByUserAndVoyage')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = new ReviewService($repo, $em);
        $result  = $service->submitReview(1, 10, 5, 'Great trip!');

        $this->assertTrue($result);
    }

    public function testSubmitExistingReviewUpdatesWithoutPersist(): void
    {
        $existing = $this->makeReview(1, 1, 10, 3, 'OK');

        $repo = $this->createMock(ReviewRepository::class);
        $repo->method('findByUserAndVoyage')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = new ReviewService($repo, $em);
        $result  = $service->submitReview(1, 10, 5, 'Even better now!');

        $this->assertTrue($result);
        $this->assertSame(5, $existing->getRating());
        $this->assertSame('Even better now!', $existing->getComment());
    }

    public function testHasUserReviewedReturnsTrueWhenExists(): void
    {
        $repo = $this->createMock(ReviewRepository::class);
        $repo->method('findByUserAndVoyage')->willReturn($this->makeReview(1, 1, 10, 4, null));

        $service = new ReviewService($repo, $this->createMock(EntityManagerInterface::class));

        $this->assertTrue($service->hasUserReviewed(1, 10));
    }

    public function testHasUserReviewedReturnsFalseWhenNotExists(): void
    {
        $repo = $this->createMock(ReviewRepository::class);
        $repo->method('findByUserAndVoyage')->willReturn(null);

        $service = new ReviewService($repo, $this->createMock(EntityManagerInterface::class));

        $this->assertFalse($service->hasUserReviewed(1, 10));
    }

    public function testGetAverageRatingDelegatesToRepository(): void
    {
        $repo = $this->createMock(ReviewRepository::class);
        $repo->method('getAverageRating')->with(10)->willReturn(4.5);

        $service = new ReviewService($repo, $this->createMock(EntityManagerInterface::class));

        $this->assertSame(4.5, $service->getAverageRating(10));
    }

    public function testGetReviewCountDelegatesToRepository(): void
    {
        $repo = $this->createMock(ReviewRepository::class);
        $repo->method('countByVoyageId')->with(10)->willReturn(7);

        $service = new ReviewService($repo, $this->createMock(EntityManagerInterface::class));

        $this->assertSame(7, $service->getReviewCount(10));
    }
}
