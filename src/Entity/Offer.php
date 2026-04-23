<?php

namespace App\Entity;

use App\Repository\OfferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OfferRepository::class)]
#[ORM\Table(name: 'offers')]
class Offer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;// @phpstan-ignore property.unusedType

    #[ORM\ManyToOne(inversedBy: 'offers')]
    #[ORM\JoinColumn(name: 'voyage_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Voyage $voyage = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'discount_percentage', type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $discountPercentage = null;

    #[ORM\Column(name: 'start_date', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(name: 'end_date', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(name: 'flash_sale_ends_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $flashSaleEndsAt = null;

    #[ORM\Column(name: 'flash_sale_discount', type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $flashSaleDiscount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVoyage(): ?Voyage
    {
        return $this->voyage;
    }

    public function setVoyage(?Voyage $voyage): self
    {
        $this->voyage = $voyage;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDiscountPercentage(): ?string
    {
        return $this->discountPercentage;
    }

    public function setDiscountPercentage(?string $discountPercentage): self
    {
        $this->discountPercentage = $discountPercentage;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getFlashSaleEndsAt(): ?\DateTimeInterface { return $this->flashSaleEndsAt; }
    public function setFlashSaleEndsAt(?\DateTimeInterface $flashSaleEndsAt): self { $this->flashSaleEndsAt = $flashSaleEndsAt; return $this; }

    public function getFlashSaleDiscount(): ?string { return $this->flashSaleDiscount; }
    public function setFlashSaleDiscount(?string $flashSaleDiscount): self { $this->flashSaleDiscount = $flashSaleDiscount; return $this; }

    public function isFlashSaleActive(): bool
    {
        return $this->flashSaleEndsAt !== null && $this->flashSaleEndsAt > new \DateTime();
    }
}
