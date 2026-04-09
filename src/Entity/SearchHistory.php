<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'search_history')]
class SearchHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id')]
    private int $userId = 0;

    #[ORM\Column(name: 'search_query', length: 255)]
    private string $searchQuery = '';

    #[ORM\Column(name: 'search_type', length: 50)]
    private string $searchType = '';

    #[ORM\Column(name: 'search_time', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $searchTime;

    #[ORM\Column(name: 'results_found', type: Types::INTEGER)]
    private int $resultsFound = 0;

    public function __construct()
    {
        $this->searchTime = new \DateTime();
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

    public function getSearchQuery(): string
    {
        return $this->searchQuery;
    }

    public function setSearchQuery(string $searchQuery): self
    {
        $this->searchQuery = $searchQuery;
        return $this;
    }

    public function getSearchType(): string
    {
        return $this->searchType;
    }

    public function setSearchType(string $searchType): self
    {
        $this->searchType = $searchType;
        return $this;
    }

    public function getSearchTime(): \DateTimeInterface
    {
        return $this->searchTime;
    }

    public function setSearchTime(\DateTimeInterface $searchTime): self
    {
        $this->searchTime = $searchTime;
        return $this;
    }

    public function getResultsFound(): int
    {
        return $this->resultsFound;
    }

    public function setResultsFound(int $resultsFound): self
    {
        $this->resultsFound = $resultsFound;
        return $this;
    }
}