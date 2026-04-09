<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_documents')]
class UserDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id')]
    private int $userId = 0;

    #[ORM\Column(name: 'first_name', length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(name: 'date_of_birth', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateOfBirth = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nationality = null;

    #[ORM\Column(name: 'passport_number', length: 255, nullable: true)]
    private ?string $passportNumber = null;

    #[ORM\Column(name: 'passport_expiry_date', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $passportExpiryDate = null;

    #[ORM\Column(name: 'cin_number', length: 255, nullable: true)]
    private ?string $cinNumber = null;

    #[ORM\Column(name: 'cin_creation_date', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cinCreationDate = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

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

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getDateOfBirth(): ?\DateTimeInterface
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(?\DateTimeInterface $dateOfBirth): self
    {
        $this->dateOfBirth = $dateOfBirth;
        return $this;
    }

    public function getNationality(): ?string
    {
        return $this->nationality;
    }

    public function setNationality(?string $nationality): self
    {
        $this->nationality = $nationality;
        return $this;
    }

    public function getPassportNumber(): ?string
    {
        return $this->passportNumber;
    }

    public function setPassportNumber(?string $passportNumber): self
    {
        $this->passportNumber = $passportNumber;
        return $this;
    }

    public function getPassportExpiryDate(): ?\DateTimeInterface
    {
        return $this->passportExpiryDate;
    }

    public function setPassportExpiryDate(?\DateTimeInterface $passportExpiryDate): self
    {
        $this->passportExpiryDate = $passportExpiryDate;
        return $this;
    }

    public function getCinNumber(): ?string
    {
        return $this->cinNumber;
    }

    public function setCinNumber(?string $cinNumber): self
    {
        $this->cinNumber = $cinNumber;
        return $this;
    }

    public function getCinCreationDate(): ?\DateTimeInterface
    {
        return $this->cinCreationDate;
    }

    public function setCinCreationDate(?\DateTimeInterface $cinCreationDate): self
    {
        $this->cinCreationDate = $cinCreationDate;
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