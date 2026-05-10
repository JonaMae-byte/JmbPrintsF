<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Session (form) login only — wired on the "main" firewall.
 * API login stays under JWTAuthenticationSuccessHandler; future OAuth can use a separate firewall.
 */
final class EmailVerifiedUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        if ($user->isVerified() !== true) {
            throw new CustomUserMessageAccountStatusException('Please verify your email before logging in');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
