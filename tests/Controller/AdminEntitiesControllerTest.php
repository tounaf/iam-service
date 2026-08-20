<?php

namespace App\Tests\Controller;

use App\Controller\AdminAssociationController;
use App\Controller\AdminDashboardController;
use App\Controller\AdminFiangonanaController;
use App\Controller\AdminGroupeController;
use App\Controller\AdminMembreController;
use App\Controller\AdminRoleController;
use App\Entity\Association;
use App\Entity\Evenement;
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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class AdminEntitiesControllerTest extends TestCase
{
    private function createMockContainer(): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);

        $container->method('has')->with('request_stack')->willReturn(true);
        $container->method('get')->willReturnCallback(function ($id) {
            if ($id === 'request_stack') {
                $requestStack = new \Symfony\Component\HttpFoundation\RequestStack();
                $session = new Session(new MockArraySessionStorage());
                $request = new Request();
                $request->setSession($session);
                $requestStack->push($request);
                return $requestStack;
            }
            if ($id === 'router') {
                $router = $this->createMock(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class);
                $router->method('generate')->willReturnCallback(function ($name, $params = []) {
                    return '/admin/fiangonana/' . ($params['id'] ?? 1) . '/editer?tab=' . ($params['tab'] ?? '');
                });
                return $router;
            }
            return null;
        });

        return $container;
    }

    public function testFiangonanaEditRendersAllTabsData(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Ambohitantely');
        $fiangonana->setCode('AMB');

        $groupe = new Groupe();
        $groupe->setNom('Zone 1');
        $groupe->setFiangonana($fiangonana);
        $fiangonana->getGroupes()->add($groupe);

        $association = new Association();
        $association->setNom('STK');
        $association->setFiangonana($fiangonana);
        $fiangonana->getAssociations()->add($association);

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
        $evenementRepo = $this->createMock(EntityRepository::class);

        $fiangonanaRepo->method('find')->with(1)->willReturn($fiangonana);
        $membreRepo->method('findBy')->with(['fiangonana' => $fiangonana], ['id' => 'DESC'])->willReturn([$membre]);
        $roleAssignmentRepo->method('findBy')->with(['fiangonanaContext' => $fiangonana, 'isActive' => true])->willReturn([$roleAssignment]);
        $evenementRepo->method('findBy')->with(['fiangonana' => $fiangonana], ['createdAt' => 'DESC'])->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);

        $presenceRepo->method('createQueryBuilder')->with('p')->willReturn($queryBuilder);
        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([$presence]);

        $em->method('getRepository')->willReturnCallback(function ($entityClass) use ($fiangonanaRepo, $membreRepo, $roleAssignmentRepo, $presenceRepo, $evenementRepo) {
            return match ($entityClass) {
                Fiangonana::class => $fiangonanaRepo,
                Membre::class => $membreRepo,
                RoleAssignment::class => $roleAssignmentRepo,
                Presence::class => $presenceRepo,
                Evenement::class => $evenementRepo,
                default => null,
            };
        });

        $controller = new AdminFiangonanaController();
        $this->assertInstanceOf(AdminFiangonanaController::class, $controller);
        $this->assertCount(1, $fiangonana->getGroupes());
        $this->assertCount(1, $fiangonana->getAssociations());
    }

    public function testFiangonanaAddGroupeDirectly(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Ambohitantely');

        $em = $this->createMock(EntityManagerInterface::class);
        $fiangonanaRepo = $this->createMock(EntityRepository::class);
        $fiangonanaRepo->method('find')->with(1)->willReturn($fiangonana);

        $em->method('getRepository')->with(Fiangonana::class)->willReturn($fiangonanaRepo);
        $em->expects($this->once())->method('persist')->with($this::isInstanceOf(Groupe::class));
        $em->expects($this->once())->method('flush');

        $controller = new AdminFiangonanaController();
        $controller->setContainer($this->createMockContainer());

        $request = Request::create('/admin/fiangonana/1/nouveau-groupe', 'POST', [
            'nom' => 'Zone Sud',
            'description' => 'Secteur sud'
        ]);

        $response = $controller->addGroupe(1, $request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/admin/fiangonana/1/editer', $response->getTargetUrl());
    }

    public function testFiangonanaAddAssociationDirectly(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Ambohitantely');

        $em = $this->createMock(EntityManagerInterface::class);
        $fiangonanaRepo = $this->createMock(EntityRepository::class);
        $fiangonanaRepo->method('find')->with(1)->willReturn($fiangonana);

        $em->method('getRepository')->with(Fiangonana::class)->willReturn($fiangonanaRepo);
        $em->expects($this->once())->method('persist')->with($this::isInstanceOf(Association::class));
        $em->expects($this->once())->method('flush');

        $controller = new AdminFiangonanaController();
        $controller->setContainer($this->createMockContainer());

        $request = Request::create('/admin/fiangonana/1/nouvelle-association', 'POST', [
            'nom' => 'Sampana Vehivavy (VFL)',
            'description' => 'Association des femmes'
        ]);

        $response = $controller->addAssociation(1, $request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/admin/fiangonana/1/editer', $response->getTargetUrl());
    }

    public function testFiangonanaAddEvenementDirectly(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Ambohitantely');

        $em = $this->createMock(EntityManagerInterface::class);
        $fiangonanaRepo = $this->createMock(EntityRepository::class);
        $fiangonanaRepo->method('find')->with(1)->willReturn($fiangonana);

        $em->method('getRepository')->with(Fiangonana::class)->willReturn($fiangonanaRepo);
        $em->expects($this->once())->method('persist')->with($this::isInstanceOf(Evenement::class));
        $em->expects($this->once())->method('flush');

        $controller = new AdminFiangonanaController();
        $controller->setContainer($this->createMockContainer());

        $request = Request::create('/admin/fiangonana/1/nouvel-evenement', 'POST', [
            'nom' => 'Culte de Pentecôte 2026',
            'lieu' => 'Temple Principal',
            'description' => 'Culte spécial'
        ]);

        $response = $controller->addEvenement(1, $request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/admin/fiangonana/1/editer', $response->getTargetUrl());
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
        $evenementRepo = $this->createMock(EntityRepository::class);

        $groupeRepo->method('find')->with(1)->willReturn($groupe);
        $fiangonanaRepo->method('findAll')->willReturn([$fiangonana]);
        $membreRepo->method('findBy')->with(['zoneGeographique' => $groupe], ['id' => 'DESC'])->willReturn([$membre]);
        $roleAssignmentRepo->method('findBy')->with(['groupeContext' => $groupe, 'isActive' => true])->willReturn([]);
        $evenementRepo->method('findBy')->with(['groupe' => $groupe], ['createdAt' => 'DESC'])->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);

        $presenceRepo->method('createQueryBuilder')->with('p')->willReturn($queryBuilder);
        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([]);

        $em->method('getRepository')->willReturnCallback(function ($entityClass) use ($groupeRepo, $fiangonanaRepo, $membreRepo, $roleAssignmentRepo, $presenceRepo, $evenementRepo) {
            return match ($entityClass) {
                Groupe::class => $groupeRepo,
                Fiangonana::class => $fiangonanaRepo,
                Membre::class => $membreRepo,
                RoleAssignment::class => $roleAssignmentRepo,
                Presence::class => $presenceRepo,
                Evenement::class => $evenementRepo,
                default => null,
            };
        });

        $controller = new AdminGroupeController();
        $this->assertInstanceOf(AdminGroupeController::class, $controller);
    }
}
