<?php

namespace App\Entity;

use App\Repository\WaitlistEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WaitlistEntryRepository::class)]
#[ORM\Table(name: 'waitlist_entries')]
#[ORM\UniqueConstraint(name: 'uniq_user_voyage_waitlist', columns: ['user_id', 'voyage_id'])]
class WaitlistEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id')]
    private int $userId = 0;

    #[ORM\Column(name: 'voyage_id')]
    private int $voyageId = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column]
    private bool $notified = false;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUserId(): int { return $this->userId; }
    public function setUserId(int $userId): self { $this->userId = $userId; return $this; }

    public function getVoyageId(): int { return $this->voyageId; }
    public function setVoyageId(int $voyageId): self { $this->voyageId = $voyageId; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function isNotified(): bool { return $this->notified; }
    public function setNotified(bool $notified): self { $this->notified = $notified; return $this; }
}
