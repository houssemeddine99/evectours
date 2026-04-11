<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'associations')]
class Association
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;// @phpstan-ignore property.unusedType

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'company_code', length: 50, unique: true)]
    private string $companyCode = '';

    #[ORM\Column(name: 'discount_rate', type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $discountRate = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getCompanyCode(): string
    {
        return $this->companyCode;
    }

    public function setCompanyCode(string $companyCode): self
    {
        $this->companyCode = $companyCode;
        return $this;
    }

    public function getDiscountRate(): string
    {
        return $this->discountRate;
    }

    public function setDiscountRate(string $discountRate): self
    {
        $this->discountRate = $discountRate;
        return $this;
    }
}