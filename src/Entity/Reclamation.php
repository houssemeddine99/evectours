<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reclamations')]
class Reclamation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'reservation_id')]
    private int $reservationId = 0;

    #[ORM\Column(name: 'user_id')]
    private int $userId = 0;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(name: 'reclamation_date', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $reclamationDate = null;

    #[ORM\Column(length: 20)]
    private string $status = 'OPEN';

    #[ORM\Column(length: 10)]
    private string $priority = 'MEDIUM';

    #[ORM\Column(name: 'admin_response', type: Types::TEXT, nullable: true)]
    private ?string $adminResponse = null;

    #[ORM\Column(name: 'response_date', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $responseDate = null;

    #[ORM\Column(name: 'resolution_date', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resolutionDate = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'response_deadline', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $responseDeadline = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReservationId(): int
    {
        return $this->reservationId;
    }

    public function setReservationId(int $reservationId): self
    {
        $this->reservationId = $reservationId;
        return $this;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getReclamationDate(): ?\DateTimeInterface
    {
        return $this->reclamationDate;
    }

    public function setReclamationDate(?\DateTimeInterface $reclamationDate): self
    {
        $this->reclamationDate = $reclamationDate;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getAdminResponse(): ?string
    {
        return $this->adminResponse;
    }

    public function setAdminResponse(?string $adminResponse): self
    {
        $this->adminResponse = $adminResponse;
        return $this;
    }

    public function getResponseDate(): ?\DateTimeInterface
    {
        return $this->responseDate;
    }

    public function setResponseDate(?\DateTimeInterface $responseDate): self
    {
        $this->responseDate = $responseDate;
        return $this;
    }

    public function getResolutionDate(): ?\DateTimeInterface
    {
        return $this->resolutionDate;
    }

    public function setResolutionDate(?\DateTimeInterface $resolutionDate): self
    {
        $this->resolutionDate = $resolutionDate;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getResponseDeadline(): ?\DateTimeInterface
    {
        return $this->responseDeadline;
    }

    public function setResponseDeadline(?\DateTimeInterface $responseDeadline): self
    {
        $this->responseDeadline = $responseDeadline;
        return $this;
    }

    public function isOverdue(): bool
    {
        if ($this->responseDeadline === null) {
            return false;
        }
        return new \DateTime() > $this->responseDeadline
            && !in_array($this->status, ['RESOLVED', 'CLOSED'], true);
    }

    public function getSlaHoursRemaining(): float
    {
        if ($this->responseDeadline === null) {
            return 0;
        }
        $diff = (new \DateTime())->diff($this->responseDeadline);
        $hours = ($diff->days * 24) + $diff->h + ($diff->i / 60);
        return $diff->invert ? -$hours : $hours;
    }
}