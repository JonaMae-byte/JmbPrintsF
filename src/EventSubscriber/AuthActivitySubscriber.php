<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class AuthActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $this->createLogFromUser($event->getUser(), 'Login');
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if ($token === null) {
            return;
        }

        $this->createLogFromUser($token->getUser(), 'Logout');
    }

    private function createLogFromUser(mixed $securityUser, string $action): void
    {
        if (!$securityUser instanceof User) {
            return;
        }

        $roleLabel = in_array('ROLE_ADMIN', $securityUser->getRoles(), true) ? 'Admin' : 'Staff';
        $userLabel = sprintf('%s (ID: %d)', $securityUser->getUserIdentifier(), (int) $securityUser->getId());

        $log = new ActivityLog();
        $log->setAction($action);
        $log->setUser($userLabel);
        $log->setDetails(sprintf('Role: %s', $roleLabel));
        $log->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($log);
        $this->em->flush();
    }
}

