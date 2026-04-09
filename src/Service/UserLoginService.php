<?php

namespace App\Service;

use App\Entity\UserLogin;
use App\Repository\UserLoginRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class UserLoginService
{
    public function __construct(
        private readonly UserLoginRepository $userLoginRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Record a user login
     */
    public function recordLogin(int $userId, string $method, ?string $ipAddress = null, ?string $userAgent = null): UserLogin
    {
        $userLogin = new UserLogin();
        $userLogin->setUserId($userId);
        $userLogin->setLoginMethod($method);
        $userLogin->setLoginTime(new \DateTime());
        $userLogin->setIpAddress($ipAddress);
        $userLogin->setUserAgent($userAgent);

        $this->entityManager->persist($userLogin);
        $this->entityManager->flush();

        return $userLogin;
    }

    /**
     * Get user's login history
     */
    public function getUserLogins(int $userId): array
    {
        return $this->safeExecute(fn () => $this->userLoginRepository->findByUserId($userId), []);
    }

    /**
     * Get user's last login
     */
    public function getLastLogin(int $userId): ?UserLogin
    {
        return $this->safeExecute(fn () => $this->userLoginRepository->findLastLoginByUserId($userId));
    }

    /**
     * Get login statistics
     */
    public function getLoginStatistics(): array
    {
        return $this->safeExecute(fn () => $this->userLoginRepository->getLoginStatistics(), []);
    }

    /**
     * Safely execute a callback with error handling
     */
    private function safeExecute(callable $callback, mixed $default = []): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->logger?->error('UserLoginService error', ['error' => $e->getMessage()]);
            return $default;
        }
    }
}