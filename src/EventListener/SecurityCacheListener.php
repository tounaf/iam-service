<?php

namespace App\EventListener;

use App\Entity\Membre;
use App\Service\PermissionResolver;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class SecurityCacheListener
{
    private PermissionResolver $permissionResolver;

    public function __construct(PermissionResolver $permissionResolver)
    {
        $this->permissionResolver = $permissionResolver;
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof Membre) {
            // Build and store feature permissions in cache upon successful login
            $this->permissionResolver->getGrantedFeatures($user);
        }
    }

    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if ($token !== null) {
            $user = $token->getUser();
            if ($user instanceof Membre) {
                // Delete feature permissions cache upon logout
                $this->permissionResolver->invalidateCache($user);
            }
        }
    }
}
