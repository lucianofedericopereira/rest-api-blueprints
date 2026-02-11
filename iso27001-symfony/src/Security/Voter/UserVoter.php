<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A.9: Fine-grained access control for User resources.
 * Enforces that users can view/edit their own profile; admins can manage all.
 */
final class UserVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();
        if (!$currentUser instanceof UserInterface) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        // Admins can do everything
        if (in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => true, // Any authenticated user can view
            self::EDIT => $currentUser->getUserIdentifier() === $targetUser->getUserIdentifier(),
            self::DELETE => false, // Only admins — already handled above
            default => false,
        };
    }
}
