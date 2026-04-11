<?php

namespace App\Entity;

use App\Repository\VoyageImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoyageImageRepository::class)]
#[ORM\Table(name: 'voyage_images')]
class VoyageImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;// @phpstan-ignore property.unusedType

    #[ORM\Column(name: 'voyage_id')]
    private int $voyageId = 0;

    #[ORM\Column(name: 'image_url', length: 500)]
    private string $imageUrl = '';

    #[ORM\Column(name: 'cloudinary_public_id', length: 255)]
    private string $cloudinaryPublicId = '';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getCloudinaryPublicId(): string
    {
        return $this->cloudinaryPublicId;
    }

    public function setCloudinaryPublicId(string $cloudinaryPublicId): self
    {
        $this->cloudinaryPublicId = $cloudinaryPublicId;
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
}