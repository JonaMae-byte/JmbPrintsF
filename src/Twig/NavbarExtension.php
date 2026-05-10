<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Centralizes navbar visibility rules so role changes stay in one place.
 * When true, the top bar shows the account dropdown (Profile, Logout, etc.).
 */
final class NavbarExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('navbar_show_profile', [$this, 'showProfileArea']),
        ];
    }

    public function showProfileArea(): bool
    {
        return $this->security->getUser() !== null;
    }
}
