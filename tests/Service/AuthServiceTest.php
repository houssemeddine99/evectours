<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\AuthService;
use App\Repository\UserRepository;
use App\Repository\AdminRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for the AuthService authentication logic.
 */
class AuthServiceTest extends TestCase
{
    private function createUser(string $email, string $username, string $plainPassword, int $id): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($plainPassword); // hashes internally
        // Set the private id property via reflection for testing purposes
        $ref = new \ReflectionClass($user);
        $prop = $ref->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($user, $id);
        return $user;
    }

    public function testAdminAuthentication(): void
    {
        $adminEmail = 'user1@exmaple.com';
        $adminPassword = 'password1';

        // Mock UserRepository to return the admin user
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneByEmail')
            ->with($adminEmail)
            ->willReturn($this->createUser($adminEmail, 'admin', $adminPassword, 1));

        // Mock AdminRepository to indicate the user is an admin
        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('findByUserId')
            ->willReturn(new \App\Entity\Admin()); // non‑null indicates admin

        $logger = $this->createMock(LoggerInterface::class);

        $authService = new AuthService($userRepo, $adminRepo, $logger);

        $result = $authService->authenticate($adminEmail, $adminPassword);
        $this->assertIsArray($result);
        $this->assertTrue($result['is_admin']);
        $this->assertSame('admin', $result['username']);
    }

    public function testUserAuthentication(): void
    {
        $userEmail = 'user2@example.com';
        $userPassword = 'password2';

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneByEmail')
            ->with($userEmail)
            ->willReturn($this->createUser($userEmail, 'user', $userPassword, 2));

        $adminRepo = $this->createMock(AdminRepository::class);
        $adminRepo->method('findByUserId')
            ->willReturn(null); // not an admin

        $logger = $this->createMock(LoggerInterface::class);

        $authService = new AuthService($userRepo, $adminRepo, $logger);

        $result = $authService->authenticate($userEmail, $userPassword);
        $this->assertIsArray($result);
        $this->assertFalse($result['is_admin']);
        $this->assertSame('user', $result['username']);
    }
}
