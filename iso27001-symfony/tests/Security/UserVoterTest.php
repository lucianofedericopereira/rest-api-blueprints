<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Domain\User\Entity\User;
use App\Security\Voter\UserVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class UserVoterTest extends TestCase
{
    private UserVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new UserVoter();
    }

    public function testAdminCanViewAnyUser(): void
    {
        $admin = $this->makeUser('admin@example.com', 'ROLE_ADMIN');
        $target = $this->makeUser('user@example.com', 'ROLE_VIEWER');

        $token = new UsernamePasswordToken($admin, 'main', $admin->getRoles());
        $result = $this->voter->vote($token, $target, [UserVoter::VIEW]);

        $this->assertSame(1, $result); // ACCESS_GRANTED
    }

    public function testAdminCanDeleteUser(): void
    {
        $admin = $this->makeUser('admin@example.com', 'ROLE_ADMIN');
        $target = $this->makeUser('user@example.com', 'ROLE_VIEWER');

        $token = new UsernamePasswordToken($admin, 'main', $admin->getRoles());
        $result = $this->voter->vote($token, $target, [UserVoter::DELETE]);

        $this->assertSame(1, $result); // ACCESS_GRANTED
    }

    public function testViewerCanViewOwnProfile(): void
    {
        $user = $this->makeUser('viewer@example.com', 'ROLE_VIEWER');

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $result = $this->voter->vote($token, $user, [UserVoter::VIEW]);

        $this->assertSame(1, $result);
    }

    public function testViewerCannotDeleteOthers(): void
    {
        $viewer = $this->makeUser('viewer@example.com', 'ROLE_VIEWER');
        $target = $this->makeUser('other@example.com', 'ROLE_VIEWER');

        $token = new UsernamePasswordToken($viewer, 'main', $viewer->getRoles());
        $result = $this->voter->vote($token, $target, [UserVoter::DELETE]);

        $this->assertSame(-1, $result); // ACCESS_DENIED
    }

    private function makeUser(string $email, string $role): User
    {
        return new User(
            id: \Symfony\Component\Uid\Uuid::v4()->toRfc4122(),
            email: $email,
            password: 'hashed',
            role: $role,
        );
    }
}
