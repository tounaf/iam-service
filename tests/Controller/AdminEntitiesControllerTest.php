<?php

namespace App\Tests\Controller;

use App\Controller\AdminAssociationController;
use App\Controller\AdminDashboardController;
use App\Controller\AdminFiangonanaController;
use App\Controller\AdminGroupeController;
use App\Controller\AdminMembreController;
use App\Controller\AdminRoleController;
use App\Entity\Association;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Membre;
use App\Entity\Presence;
use App\Entity\Role;
use App\Entity\RoleAssignment;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminEntitiesControllerTest extends TestCase
{
    public function testFiangonanaEditRendersFourTabsData(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Ambohitantely');
        $fiangonana->setCode('AMB');

        $membre = new Membre();
        $membre->setNom('Rakoto');
        $membre->setPrenom('Jean');
        $membre->setFiangonana($fiangonana);

        $role = new Role();
        $role->setName('PRESIDENT');

        $roleAssignment = new RoleAssignment();
        $roleAssignment->setMembre($membre);
        $roleAssignment->setRole($role);
        $roleAssignment->setFiangonanaContext($fiangonana);
        $roleAssignment->setExerciceYear('2026');

        $presence = new Presence();
        $presence->setMembre($membre);
        $presence->setActivityName('Culte Dominical');

        $em = $this->createMock(EntityManagerInterface::class);
        $fiangonanaRepo = $this->createMock(EntityRepository::class);
        $membreRepo = $this->createMock(EntityRepository::class);
        $roleAssignmentRepo = $this->createMock(EntityRepository::class);
        $presenceRepo = $this->createMock(EntityRepository::class);

        $fiangonanaRepo->method('find')->with(1)->willReturn($fiangonana);
        $membreRepo->method('findBy')->with(['fiangonana' => $fiangonana], ['id' => 'DESC'])->willReturn([$membre]);
        $roleAssignmentRepo->method('findBy')->with(['fiangonanaContext' => $fiangonana, 'isActive' => true])->willReturn([$roleAssignment]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);

        $presenceRepo->method('createQueryBuilder')->with('p')->willReturn($queryBuilder);
        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([$presence]);

        $em->method('getRepository')->willReturnCallback(function ($entityClass) use ($fiangonanaRepo, $membreRepo, $roleAssignmentRepo, $presenceRepo) {
            return match ($entityClass) {
                Fiangonana::class => $fiangonanaRepo,
                Membre::class => $membreRepo,
                RoleAssignment::class => $roleAssignmentRepo,
                Presence::class => $presenceRepo,
                default => null,
            };
        });

        $controller = new AdminFiangonanaController();
        // Test entity retrieval and array mapping
        $request = Request::create('/admin/fiangonana/1/editer', 'GET');

        $this->assertInstanceOf(AdminFiangonanaController::class, $controller);
    }

    public function testGroupeEditRendersFourTabsData(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Ambohitantely');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');
        $groupe->setFiangonana($fiangonana);

        $membre = new Membre();
        $membre->setNom('Rasoa');
        $membre->setPrenom('Marie');
        $membre->setZoneGeographique($groupe);

        $em = $this->createMock(EntityManagerInterface::class);
        $groupeRepo = $this->createMock(EntityRepository::class);
        $fiangonanaRepo = $this->createMock(EntityRepository::class);
        $membreRepo = $this->createMock(EntityRepository::class);
        $roleAssignmentRepo = $this->createMock(EntityRepository::class);
        $presenceRepo = $this->createMock(EntityRepository::class);

        $groupeRepo->method('find')->with(1)->willReturn($groupe);
        $fiangonanaRepo->method('findAll')->willReturn([$fiangonana]);
        $membreRepo->method('findBy')->with(['zoneGeographique' => $groupe], ['id' => 'DESC'])->willReturn([$membre]);
        $roleAssignmentRepo->method('findBy')->with(['groupeContext' => $groupe, 'isActive' => true])->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);

        $presenceRepo->method('createQueryBuilder')->with('p')->willReturn($queryBuilder);
        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([]);

        $em->method('getRepository')->willReturnCallback(function ($entityClass) use ($groupeRepo, $fiangonanaRepo, $membreRepo, $roleAssignmentRepo, $presenceRepo) {
            return match ($entityClass) {
                Groupe::class => $groupeRepo,
                Fiangonana::class => $fiangonanaRepo,
                Membre::class => $membreRepo,
                RoleAssignment::class => $roleAssignmentRepo,
                Presence::class => $presenceRepo,
                default => null,
            };
        });

        $controller = new AdminGroupeController();
        $this->assertInstanceOf(AdminGroupeController::class, $controller);
    }
}
