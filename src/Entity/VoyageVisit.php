<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'voyage_visits')]
class VoyageVisit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id')]
    private int $userId = 0;

    #[ORM\Column(name: 'voyage_id')]
    private int $voyageId = 0;

    #[ORM\Column(name: 'visit_time', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $visitTime;

    #[ORM\Column(length: 50)]
    private string $source = 'direct';

    #[ORM\Column(name: 'view_duration_seconds', type: Types::INTEGER)]
    private int $viewDurationSeconds = 0;

    public function __construct()
    {
        $this->visitTime = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getVoyageId(): int
    {
        return $this->voyageId;
    }

    public function setVoyageId(int $voyageId): self
    {
        $this->voyageId = $voyageId;
        return $this;
    }

    public function getVisitTime(): \DateTimeInterface
    {
        return $this->visitTime;
    }

    public function setVisitTime(\DateTimeInterface $visitTime): self
    {
        $this->visitTime = $visitTime;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getViewDurationSeconds(): int
    {
        return $this->viewDurationSeconds;
    }

    public function setViewDurationSeconds(int $viewDurationSeconds): self
    {
        $this->viewDurationSeconds = $viewDurationSeconds;
        return $this;
    }
}