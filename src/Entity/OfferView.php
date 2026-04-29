<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'offer_views')]
class OfferView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id')]
    private int $userId = 0;

    #[ORM\Column(name: 'offer_id')]
    private int $offerId = 0;

    #[ORM\Column(name: 'view_time', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $viewTime;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $clicked = false;

    public function __construct()
    {
        $this->viewTime = new \DateTime();
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

    public function getOfferId(): int
    {
        return $this->offerId;
    }

    public function setOfferId(int $offerId): self
    {
        $this->offerId = $offerId;
        return $this;
    }

    public function getViewTime(): \DateTimeInterface
    {
        return $this->viewTime;
    }

    public function setViewTime(\DateTimeInterface $viewTime): self
    {
        $this->viewTime = $viewTime;
        return $this;
    }

    public function isClicked(): bool
    {
        return $this->clicked;
    }

    public function setClicked(bool $clicked): self
    {
        $this->clicked = $clicked;
        return $this;
    }
}