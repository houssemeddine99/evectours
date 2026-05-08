<?php

namespace App\Service;

use App\Entity\UserDocument;
use App\Repository\UserDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class UserDocumentService
{
    public function __construct(
        private readonly UserDocumentRepository $userDocumentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Create or update user document
     * @param array<mixed> $data
     */
    public function saveDocument(int $userId, array $data): UserDocument
    {
        $documents = $this->userDocumentRepository->findByUserId($userId);
        $document = $documents[0] ?? new UserDocument();

        $document->setUserId($userId);
        $document->setFirstName($data['first_name'] ?? null);
        $document->setLastName($data['last_name'] ?? null);
        $document->setDateOfBirth(isset($data['date_of_birth']) ? new \DateTime($data['date_of_birth']) : null);
        $document->setNationality($data['nationality'] ?? null);
        $document->setPassportNumber($data['passport_number'] ?? null);
        $document->setPassportExpiryDate(isset($data['passport_expiry_date']) ? new \DateTime($data['passport_expiry_date']) : null);
        $document->setCinNumber($data['cin_number'] ?? null);
        $document->setCinCreationDate(isset($data['cin_creation_date']) ? new \DateTime($data['cin_creation_date']) : null);
        $document->setUpdatedAt(new \DateTime());

        if (!$document->getId()) {
            $document->setCreatedAt(new \DateTime());
        }

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    /**
     * Get document by user ID
     */
    public function getDocumentByUserId(int $userId): ?UserDocument
    {
        $documents = $this->safeExecute(fn () => $this->userDocumentRepository->findByUserId($userId), []);
        return $documents[0] ?? null;
    }

    /**
     * Check if passport is expiring soon
     */
    public function isPassportExpiring(int $userId, int $months = 6): bool
    {
        $document = $this->getDocumentByUserId($userId);
        if (!$document || !$document->getPassportExpiryDate()) {
            return false;
        }

        $expiryDate = $document->getPassportExpiryDate();
        $warningDate = new \DateTime("+{$months} months");

        return $expiryDate <= $warningDate;
    }

    /**
     * Get all expiring passports
     * @return array<mixed>
     */
    public function getExpiringPassports(int $months = 6): array
    {
        return $this->safeExecute(fn () => $this->userDocumentRepository->findExpiringPassports($months), []);
    }

    /**
     * Safely execute a callback with error handling
     */
    private function safeExecute(callable $callback, mixed $default = []): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->logger?->error('UserDocumentService error', ['error' => $e->getMessage()]);
            return $default;
        }
    }
}