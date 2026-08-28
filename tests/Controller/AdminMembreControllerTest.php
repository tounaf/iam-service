<?php

namespace App\Tests\Controller;

use App\Controller\AdminMembreController;
use App\Entity\Association;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Membre;
use App\Entity\Role;
use App\Entity\RoleAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AdminMembreControllerTest extends TestCase
{
    private function createMockContainer(): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);

        $container->method('has')->with('request_stack')->willReturn(true);
        $container->method('get')->willReturnCallback(function ($id) {
            if ($id === 'request_stack') {
                $requestStack = new RequestStack();
                $session = new Session(new MockArraySessionStorage());
                $request = new Request();
                $request->setSession($session);
                $requestStack->push($request);
                return $requestStack;
            }
            if ($id === 'router') {
                $router = $this->createMock(UrlGeneratorInterface::class);
                $router->method('generate')->willReturnCallback(function ($name, $params = []) {
                    return '/admin/membres/' . ($params['id'] ?? 1) . '/editer';
                });
                return $router;
            }
            return null;
        });

        return $container;
    }

    public function testNewMembreWithDirectAssociation(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Ambohitantely');

        $groupe = new Groupe();
        $groupe->setNom('Zone 1');

        $association = new Association();
        $association->setNom('KTM');

        $em = $this->createMock(EntityManagerInterface::class);
        $fiangonanaRepo = $this->createMock(EntityRepository::class);
        $groupeRepo = $this->createMock(EntityRepository::class);
        $assocRepo = $this->createMock(EntityRepository::class);

        $fiangonanaRepo->method('find')->with(1)->willReturn($fiangonana);
        $groupeRepo->method('find')->with(2)->willReturn($groupe);
        $assocRepo->method('find')->with(3)->willReturn($association);

        $em->method('getRepository')->willReturnCallback(function ($entityClass) use ($fiangonanaRepo, $groupeRepo, $assocRepo) {
            return match ($entityClass) {
                Fiangonana::class => $fiangonanaRepo,
                Groupe::class => $groupeRepo,
                Association::class => $assocRepo,
                default => null,
            };
        });

        $em->expects($this->once())->method('persist')->with($this->callback(function ($m) use ($fiangonana, $groupe, $association) {
            return $m instanceof Membre
                && $m->getNom() === 'Rabe'
                && $m->getPrenom() === 'Soa'
                && $m->getDateNaissance()?->format('Y-m-d') === '1995-05-15'
                && $m->getAge() !== null
                && $m->getFiangonana() === $fiangonana
                && $m->getZoneGeographique() === $groupe
                && $m->getAssociations()->contains($association);
        }));
        $em->expects($this->once())->method('flush');

        $controller = new AdminMembreController();
        $controller->setContainer($this->createMockContainer());

        $request = Request::create('/admin/membres/nouveau', 'POST', [
            'nom' => 'Rabe',
            'prenom' => 'Soa',
            'email' => 'rabe.soa@example.com',
            'dateNaissance' => '1995-05-15',
            'fiangonana_id' => 1,
            'groupe_id' => 2,
            'association_ids' => [3],
        ]);

        $response = $controller->new($request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testAddTemporalRoleAssignmentWithGroupeContext(): void
    {
        $membre = new Membre();
        $membre->setNom('Rasoa');
        $membre->setPrenom('Marie');

        $role = new Role();
        $role->setName('PRESIDENT');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $em = $this->createMock(EntityManagerInterface::class);
        $membreRepo = $this->createMock(EntityRepository::class);
        $roleRepo = $this->createMock(EntityRepository::class);
        $groupeRepo = $this->createMock(EntityRepository::class);

        $membreRepo->method('find')->with(10)->willReturn($membre);
        $roleRepo->method('find')->with(5)->willReturn($role);
        $groupeRepo->method('find')->with(12)->willReturn($groupe);

        $em->method('getRepository')->willReturnCallback(function ($entityClass) use ($membreRepo, $roleRepo, $groupeRepo) {
            return match ($entityClass) {
                Membre::class => $membreRepo,
                Role::class => $roleRepo,
                Groupe::class => $groupeRepo,
                default => null,
            };
        });

        $em->expects($this->once())->method('persist')->with($this->callback(function ($ra) use ($membre, $role, $groupe) {
            return $ra instanceof RoleAssignment
                && $ra->getMembre() === $membre
                && $ra->getRole() === $role
                && $ra->getGroupeContext() === $groupe
                && $ra->getStartDate()->format('Y-m-d') === '2026-01-20'
                && $ra->getEndDate()?->format('Y-m-d') === '2026-12-12'
                && $ra->getExerciceYear() === '2026';
        }));
        $em->expects($this->once())->method('flush');

        $controller = new AdminMembreController();
        $controller->setContainer($this->createMockContainer());

        $request = Request::create('/admin/membres/10/editer', 'POST', [
            'form_action' => 'add_role',
            'role_id' => 5,
            'context_type' => 'groupe',
            'context_id' => 12,
            'start_date' => '2026-01-20',
            'end_date' => '2026-12-12',
            'exercice_year' => '2026',
        ]);

        $response = $controller->edit(10, $request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }
}
