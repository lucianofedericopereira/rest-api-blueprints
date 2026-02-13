<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Audit\AuditServiceInterface;
use App\Domain\User\DTO\CreateUserRequest;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\UserService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserRegistrationTest extends TestCase
{
    private UserRepositoryInterface|MockObject $repository;
    private UserPasswordHasherInterface|MockObject $hasher;
    private EventDispatcherInterface|MockObject $dispatcher;
    private AuditServiceInterface|MockObject $auditService;
    private UserService $service;

    protected function setUp(): void
    {
        $this->repository  = $this->createMock(UserRepositoryInterface::class);
        $this->hasher      = $this->createMock(UserPasswordHasherInterface::class);
        $this->dispatcher  = $this->createMock(EventDispatcherInterface::class);
        $this->auditService = $this->createMock(AuditServiceInterface::class);

        $this->service = new UserService(
            $this->repository,
            $this->hasher,
            $this->dispatcher,
            $this->auditService,
        );
    }

    public function testCreateUserSuccessfully(): void
    {
        $dto = new CreateUserRequest(
            email: 'newuser@example.com',
            password: 'Str0ng!Pass123',
            role: 'ROLE_VIEWER',
        );

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with('newuser@example.com')
            ->willReturn(null);

        $this->hasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $this->repository->expects($this->once())
            ->method('save');

        $this->dispatcher->expects($this->once())
            ->method('dispatch');

        $user = $this->service->createUser($dto);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('ROLE_VIEWER', $user->getRole());
    }

    public function testCreateUserThrowsWhenEmailAlreadyExists(): void
    {
        $dto = new CreateUserRequest('existing@example.com', 'Pass123!X@ab', 'ROLE_VIEWER');

        $existingUser = $this->createMock(User::class);
        $this->repository->method('findByEmail')->willReturn($existingUser);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already exists');

        $this->service->createUser($dto);
    }

    public function testDeleteUserReturnsFalseWhenNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $result = $this->service->deleteUser('non-existent-id');

        $this->assertFalse($result);
    }

    public function testDeleteUserSoftDeletes(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('usr_123');
        $this->repository->method('findById')->willReturn($user);

        $user->expects($this->once())->method('softDelete');
        $this->repository->expects($this->once())->method('save');

        $result = $this->service->deleteUser('usr_123');

        $this->assertTrue($result);
    }
}
