<?php

namespace App\Tests\Service;

use App\Entity\Feature;
use App\Entity\Membre;
use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\RoleAssignment;
use App\EventListener\SecurityCacheListener;
use App\Service\PermissionResolver;
use App\Twig\FeatureExtension;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class PermissionResolverAndCacheTest extends TestCase
{
    public function testPermissionResolverCalculatesAndCachesFeatures(): void
    {
        $membre = new Membre();
        $ref = new \ReflectionProperty(Membre::class, 'id');
        $ref->setValue($membre, 42);
        $membre->setEmail('member@example.com');

        $feature = new Feature();
        $feature->setCode('MEMBER_EVENT_SCAN');

        $permission = new Permission();
        $permission->setFeature($feature);
        $permission->setAction('READ');

        $role = new Role();
        $role->setName('OPERATEUR_SCAN');
        $role->getPermissions()->add($permission);

        $assignment = new RoleAssignment();
        $assignment::class;
        $assignment->setMembre($membre);
        $assignment->setRole($role);
        $assignment->setIsActive(true);

        $roleAssignmentRepo = $this->createMock(EntityRepository::class);
        $roleAssignmentRepo->method('findBy')->willReturn([$assignment]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')
            ->with(RoleAssignment::class)
            ->willReturn($roleAssignmentRepo);

        $cache = new ArrayAdapter();
        $resolver = new PermissionResolver($entityManager, $cache);

        // 1. Calculate and cache
        $granted = $resolver->getGrantedFeatures($membre);
        $this->assertArrayHasKey('MEMBER_EVENT_SCAN', $granted);
        $this->assertEquals(['READ'], $granted['MEMBER_EVENT_SCAN']);

        // 2. Test isFeatureGranted
        $this->assertTrue($resolver->isFeatureGranted($membre, 'MEMBER_EVENT_SCAN', 'READ'));
        $this->assertFalse($resolver->isFeatureGranted($membre, 'MEMBER_EVENT_SCAN', 'WRITE'));
        $this->assertFalse($resolver->isFeatureGranted($membre, 'ADMIN_MENU_MEMBRES'));

        // 3. Invalidate cache
        $resolver->invalidateCache($membre);
        $cacheKey = $resolver->getCacheKey($membre);
        $this->assertFalse($cache->hasItem($cacheKey));
    }

    public function testSecurityCacheListenerOnLoginAndLogout(): void
    {
        $membre = new Membre();
        $ref = new \ReflectionProperty(Membre::class, 'id');
        $ref->setValue($membre, 99);

        $resolver = $this->createMock(PermissionResolver::class);
        $resolver->expects($this->once())
            ->method('getGrantedFeatures')
            ->with($membre);
        $resolver->expects($this->once())
            ->method('invalidateCache')
            ->with($membre);

        $listener = new SecurityCacheListener($resolver);

        // Login event
        $loginEvent = $this->createMock(LoginSuccessEvent::class);
        $loginEvent->method('getUser')->willReturn($membre);
        $listener->onLoginSuccess($loginEvent);

        // Logout event
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($membre);
        $logoutEvent = $this->createMock(LogoutEvent::class);
        $logoutEvent->method('getToken')->willReturn($token);
        $listener->onLogout($logoutEvent);
    }

    public function testTwigFeatureExtension(): void
    {
        $membre = new Membre();
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($membre);

        $resolver = $this->createMock(PermissionResolver::class);
        $resolver->method('isFeatureGranted')->with($membre, 'ADMIN_MENU_MEMBRES', null)->willReturn(true);
        $resolver->method('getGrantedFeatures')->with($membre)->willReturn(['ADMIN_MENU_MEMBRES' => ['READ']]);

        $extension = new FeatureExtension($security, $resolver);

        $this->assertTrue($extension->isFeatureGranted('ADMIN_MENU_MEMBRES'));
        $this->assertEquals(['ADMIN_MENU_MEMBRES' => ['READ']], $extension->getGrantedFeatures());
    }
}
