<?php

namespace App\Service;

use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;

class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function authenticate(string $email, string $plainPassword): ?array
    {
        $email = strtolower(trim($email));

        if ($email === '' || $plainPassword === '') {
            $this->logger->warning('Login attempt with empty email or password');
            return null;
        }

        $this->logger->info('Login attempt started', ['email' => $email]);

        try {
            $user = $this->userRepository->findOneByEmail($email);
            
            if ($user !== null) {
                $this->logger->debug('User found in database', [
                    'email' => $email,
                    'user_id' => $user->getId(),
                    'username' => $user->getUsername()
                ]);
            } else {
                $this->logger->info('User not found in database, checking fallback', ['email' => $email]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Database error during authentication', [
                'email' => $email,
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);
            $user = null;
        }

        if ($user !== null && $this->isPasswordValid($plainPassword, $user->getPassword())) {
            $this->logger->info('Login successful', [
                'email' => $email,
                'user_id' => $user->getId(),
                'username' => $user->getUsername(),
                'auth_method' => 'database'
            ]);
            
            return [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'is_admin' => $this->isAdmin($user->getId()),
            ];
        }

        if ($user !== null) {
            $this->logger->warning('Login failed - incorrect password', [
                'email' => $email,
                'user_id' => $user->getId()
            ]);
        }

        return $this->fallbackAuthenticate($email, $plainPassword);
    }

    public function emailExists(string $email): bool
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return false;
        }

        // First check fallback users
        $fallbackUsers = [
            ['email' => 'admin@travagir.com'],
            ['email' => 'user@travagir.com'],
        ];

        foreach ($fallbackUsers as $fallbackUser) {
            if ($fallbackUser['email'] === $email) {
                return true;
            }
        }

        // Then check database - catch connection exceptions gracefully
        try {
            $user = $this->userRepository->findOneByEmail($email);
            return $user !== null;
        } catch (\Throwable $e) {
            // Database connection error - return false to fall back to demo users
            return false;
        }
    }

    private function isPasswordValid(string $plainPassword, string $storedPassword): bool
    {
        // Check if it's a PHP password_hash
        if (password_get_info($storedPassword)['algo'] !== null) {
            return password_verify($plainPassword, $storedPassword);
        }
        
        // Check if it's a BCrypt hash (Java Spring format)
        if (strlen($storedPassword) === 60 && substr($storedPassword, 0, 4) === '$2a$') {
            return password_verify($plainPassword, $storedPassword);
        }
        
        // Check if it's a BCrypt hash with $2b$ or $2y$ prefix
        if (strlen($storedPassword) === 60 && (substr($storedPassword, 0, 4) === '$2b$' || substr($storedPassword, 0, 4) === '$2y$')) {
            return password_verify($plainPassword, $storedPassword);
        }

        // Plain text comparison (fallback)
        return hash_equals($storedPassword, $plainPassword);
    }

    private function fallbackAuthenticate(string $email, string $plainPassword): ?array
    {
        $this->logger->info('Attempting fallback authentication', ['email' => $email]);
        
        $fallbackUsers = [
            [
                'id' => 1,
                'username' => 'Demo Admin',
                'email' => 'admin@travagir.com',
                'password' => 'admin123',
            ],
            [
                'id' => 2,
                'username' => 'Demo Traveler',
                'email' => 'user@travagir.com',
                'password' => 'user123',
            ],
        ];

        foreach ($fallbackUsers as $user) {
            if ($user['email'] === $email && hash_equals($user['password'], $plainPassword)) {
                $this->logger->info('Fallback login successful', [
                    'email' => $email,
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'auth_method' => 'fallback'
                ]);
                
                unset($user['password']);
                $user['is_admin'] = ($email === 'admin@travagir.com');

                return $user;
            }
        }

        $this->logger->warning('Login failed - all authentication methods failed', [
            'email' => $email,
            'reason' => 'Invalid credentials'
        ]);

        return null;
    }

    public function register(string $username, string $email, string $plainPassword): ?array
    {
        $email = strtolower(trim($email));
        $username = trim($username);

        if ($email === '' || $username === '' || $plainPassword === '') {
            return null;
        }

        try {
            $existingUser = $this->userRepository->findOneByEmail($email);
            if ($existingUser !== null) {
                return null; // Email already exists
            }

            $user = new \App\Entity\User();
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setPassword(password_hash($plainPassword, PASSWORD_DEFAULT));
            $user->setCreatedAt(new \DateTime());

            $entityManager = $this->userRepository->getEntityManager();
            $entityManager->persist($user);
            $entityManager->flush();

            return [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function getUserById(int $userId): ?array
    {
        $user = $this->userRepository->find($userId);
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'tel' => $user->getTel(),
            'image_url' => $user->getImageUrl(),
            'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            'is_admin' => $this->isAdmin($user->getId()),
        ];
    }

    public function isAdmin(int $userId): bool
    {
        try {
            $connection = $this->userRepository->getEntityManager()->getConnection();
            $result = $connection->fetchOne('SELECT access_level FROM admins WHERE user_id = ?', [$userId]);
            return $result !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function checkPasswordForUser(int $userId, string $plainPassword): bool
    {
        $user = $this->userRepository->find($userId);
        if (!$user) {
            return false;
        }

        return $this->isPasswordValid($plainPassword, $user->getPassword());
    }

    public function updateProfile(int $userId, string $username, string $email, ?string $tel = null, ?string $imageUrl = null, ?string $newPassword = null, ?string $currentPassword = null): ?array
    {
        $username = trim($username);
        $email = strtolower(trim($email));
        $tel = $tel !== null ? trim($tel) : null;
        $imageUrl = $imageUrl !== null ? trim($imageUrl) : null;

        if ($username === '' || $email === '') {
            return null;
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return null;
        }

        if ($currentPassword !== null && $currentPassword !== '') {
            if (!$this->checkPasswordForUser($userId, $currentPassword)) {
                return null;
            }
        }

        // validate email uniqueness except existing user
        $exists = $this->userRepository->findOneByEmail($email);
        if ($exists !== null && $exists->getId() !== $userId) {
            return null;
        }

        $user->setUsername($username);
        $user->setEmail($email);
        $user->setTel($tel);
        $user->setImageUrl($imageUrl);

        if ($newPassword !== null && $newPassword !== '') {
            $user->setPassword(password_hash($newPassword, PASSWORD_DEFAULT));
        }

        try {
            $entityManager = $this->userRepository->getEntityManager();
            $entityManager->flush();

            return [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'tel' => $user->getTel(),
                'image_url' => $user->getImageUrl(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update profile', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function listUsers(): array
    {
        $users = $this->userRepository->findAll();

        return array_map(function ($user) {
            return [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'tel' => $user->getTel(),
                'image_url' => $user->getImageUrl(),
                'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $users);
    }

    public function deleteUser(int $userId): bool
    {
        $user = $this->userRepository->find($userId);
        if (!$user) {
            return false;
        }

        try {
            $entityManager = $this->userRepository->getEntityManager();
            $entityManager->remove($user);
            $entityManager->flush();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to delete user', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }
}

