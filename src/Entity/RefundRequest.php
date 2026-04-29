<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'refund_requests')]
class RefundRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]

    private ?int $id = null;

    #[ORM\Column(name: 'reclamation_id')]
    private int $reclamationId;

    #[ORM\Column(name: 'requester_id')]
    private int $requesterId;

    #[ORM\Column(name: 'reservation_id', nullable: true)]
    private ?int $reservationId = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(length: 20)]
    private string $status = 'PENDING';

    #[ORM\Column(name: 'approved_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $approvedAmount = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReclamationId(): ?int
    {
        return $this->reclamationId;
    }

    public function setReclamationId(?int $reclamationId): self
    {
        $this->reclamationId = $reclamationId;
        return $this;
    }

    public function getRequesterId(): int
    {
        return $this->requesterId;
    }

    public function setRequesterId(int $requesterId): self
    {
        $this->requesterId = $requesterId;
        return $this;
    }

    public function getReservationId(): ?int
    {
        return $this->reservationId;
    }

    public function setReservationId(?int $reservationId): self
    {
        $this->reservationId = $reservationId;
        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getApprovedAmount(): ?string
    {
        return $this->approvedAmount;
    }

    public function setApprovedAmount(?string $approvedAmount): self
    {
        $this->approvedAmount = $approvedAmount;
        return $this;
    }

    public function getEffectiveAmount(): string
    {
        return $this->approvedAmount ?? $this->amount;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}