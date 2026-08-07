<?php

namespace App\Tests\Security\Voter;

use App\Entity\Association;
use App\Entity\Fiangonana;
use App\Entity\Membre;
use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\RoleAssignment;
use App\Security\Voter\ContextualRoleVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ContextualRoleVoterTest extends TestCase
{
    private $entityManager;
    private $repository;
    private $voter;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->with(RoleAssignment::class)
            ->willReturn($this->repository);

        $this->voter = new ContextualRoleVoter($this->entityManager);
    }

    public function testVoteDeniesIfUserNotMembre(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $association = new Association();
        $result = $this->voter->vote($token, $association, ['READ']);

        $this->assertEquals(Voter::ACCESS_DENIED, $result);
    }

    public function testVoteGrantsAccessForActiveRoleAssignmentAndPermission(): void
    {
        $member = new Membre();

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($member);

        $fiangonana = $this->createMock(Fiangonana::class);
        $fiangonana->method('getId')->willReturn(1);

        $association = $this->createMock(Association::class);
        $association->method('getId')->willReturn(10);
        $association->method('getFiangonana')->willReturn($fiangonana);

        $role = new Role();
        $role->setName('CAISSIER');

        $permission = new Permission();
        $permission->setAction('READ');
        $permission->setRole($role);
        $role->getPermissions()->add($permission);

        $assignment = new RoleAssignment();
        $assignment->setMembre($member);
        $assignment->setRole($role);
        $assignment->setAssociationContext($association);
        $assignment->setIsActive(true);
        $assignment->setStartDate(new \DateTimeImmutable('-1 day'));
        $assignment->setExerciceYear('2025');

        $this->repository
            ->method('findBy')
            ->with(['membre' => $member, 'isActive' => true])
            ->willReturn([$assignment]);

        $result = $this->voter->vote($token, $association, ['READ']);

        $this->assertEquals(Voter::ACCESS_GRANTED, $result);
    }

    public function testVoteDeniesAccessIfAssignmentExpired(): void
    {
        $member = new Membre();

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($member);

        $association = $this->createMock(Association::class);
        $association->method('getId')->willReturn(10);

        $role = new Role();
        $role->setName('CAISSIER');

        $permission = new Permission();
        $permission->setAction('READ');
        $role->getPermissions()->add($permission);

        $assignment = new RoleAssignment();
        $assignment->setMembre($member);
        $assignment->setRole($role);
        $assignment->setAssociationContext($association);
        $assignment->setIsActive(true);
        $assignment->setStartDate(new \DateTimeImmutable('-5 days'));
        $assignment->setEndDate(new \DateTimeImmutable('-1 day')); // Expired!
        $assignment->setExerciceYear('2025');

        $this->repository
            ->method('findBy')
            ->with(['membre' => $member, 'isActive' => true])
            ->willReturn([$assignment]);

        $result = $this->voter->vote($token, $association, ['READ']);

        $this->assertEquals(Voter::ACCESS_DENIED, $result);
    }

    public function testVoteGrantsAccessByHierarchicalInheritance(): void
    {
        $member = new Membre();

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($member);

        $fiangonana = $this->createMock(Fiangonana::class);
        $fiangonana->method('getId')->willReturn(1);

        $association = $this->createMock(Association::class);
        $association->method('getId')->willReturn(10);
        $association->method('getFiangonana')->willReturn($fiangonana);

        $role = new Role();
        $role->setName('PRESIDENT');

        $permission = new Permission();
        $permission->setAction('READ');
        $role->getPermissions()->add($permission);

        $assignment = new RoleAssignment();
        $assignment->setMembre($member);
        $assignment->setRole($role);
        $assignment->setFiangonanaContext($fiangonana); // Church level context
        $assignment->setIsActive(true);
        $assignment->setStartDate(new \DateTimeImmutable('-1 day'));
        $assignment->setExerciceYear('2025');

        $this->repository
            ->method('findBy')
            ->with(['membre' => $member, 'isActive' => true])
            ->willReturn([$assignment]);

        // President checks reading the association (which is inside the church)
        $result = $this->voter->vote($token, $association, ['READ']);

        $this->assertEquals(Voter::ACCESS_GRANTED, $result);
    }
}
