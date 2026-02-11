<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\User\Contracts\UserRepositoryInterface;
use App\Domain\User\DTOs\CreateUserDTO;
use App\Domain\User\DTOs\UpdateUserDTO;
use App\Domain\User\Events\UserCreated;
use App\Domain\User\Events\UserDeleted;
use App\Domain\User\Events\UserUpdated;
use App\Domain\User\Models\User;
use App\Domain\User\Services\UserService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    private UserRepositoryInterface $repository;
    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->service    = new UserService($this->repository);
    }

    public function test_create_user_dispatches_user_created_event(): void
    {
        $user = $this->makeUser();

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn($user);

        $this->service->createUser(
            new CreateUserDTO('new@example.com', 'Str0ng!Pass12', 'viewer'),
        );

        Event::assertDispatched(UserCreated::class, function (UserCreated $event) use ($user) {
            return $event->userId === $user->id
                && $event->role === $user->role;
        });
    }

    public function test_create_user_throws_when_email_exists(): void
    {
        $this->repository->method('findByEmail')->willReturn($this->makeUser());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already exists');

        $this->service->createUser(
            new CreateUserDTO('existing@example.com', 'Pass123!@#AB', 'viewer'),
        );
    }

    public function test_delete_user_returns_false_when_not_found(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $result = $this->service->deleteUser('nonexistent');

        $this->assertFalse($result);
        Event::assertNotDispatched(UserDeleted::class);
    }

    public function test_delete_user_soft_deletes_and_dispatches_event(): void
    {
        $user = $this->makeUser();
        $this->repository->method('findById')->willReturn($user);
        $this->repository->expects($this->once())->method('softDelete');

        $result = $this->service->deleteUser($user->id);

        $this->assertTrue($result);
        Event::assertDispatched(UserDeleted::class, fn ($e) => $e->userId === $user->id);
    }

    public function test_update_user_dispatches_user_updated_event(): void
    {
        $user = $this->makeUser();
        $this->repository->method('findById')->willReturn($user);
        $this->repository->method('update')->willReturn($user);

        $this->service->updateUser($user->id, new UpdateUserDTO(role: 'analyst'));

        Event::assertDispatched(UserUpdated::class, fn ($e) => $e->userId === $user->id);
    }

    private function makeUser(): User
    {
        $user           = new User();
        $user->id       = (string) \Illuminate\Support\Str::uuid();
        $user->email    = 'test@example.com';
        $user->password = bcrypt('secret');
        $user->role     = 'viewer';
        return $user;
    }
}
