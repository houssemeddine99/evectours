<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reviews')]
#[ORM\UniqueConstraint(name: 'uq_review_user_voyage', columns: ['user_id', 'voyage_id'])]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id')]
    private int $userId = 0;

    #[ORM\Column(name: 'voyage_id')]
    private int $voyageId = 0;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $rating = 5;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUserId(): int { return $this->userId; }
    public function setUserId(int $v): static { $this->userId = $v; return $this; }
    public function getVoyageId(): int { return $this->voyageId; }
    public function setVoyageId(int $v): static { $this->voyageId = $v; return $this; }
    public function getRating(): int { return $this->rating; }
    public function setRating(int $v): static { $this->rating = max(1, min(5, $v)); return $this; }
    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $v): static { $this->comment = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
