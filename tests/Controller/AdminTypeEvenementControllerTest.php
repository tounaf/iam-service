<?php

namespace App\Tests\Controller;

use App\Controller\AdminTypeEvenementController;
use App\Entity\TypeEvenement;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class AdminTypeEvenementControllerTest extends TestCase
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
                $router->method('generate')->willReturn('/admin/types-evenement');
                return $router;
            }
            return null;
        });

        return $container;
    }

    public function testNewTypeEvenementSavesAndRedirects(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(TypeEvenement::class));
        $em->expects($this->once())->method('flush');

        $controller = new AdminTypeEvenementController();
        $controller->setContainer($this->createMockContainer());

        $request = Request::create('/admin/types-evenement/nouveau', 'POST', [
            'nom' => 'Retraite Spirituelle',
            'code' => 'RETRAITE',
            'description' => 'Événement de retraite spiritual'
        ]);

        $response = $controller->new($request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/admin/types-evenement', $response->getTargetUrl());
    }

    public function testEditTypeEvenementUpdatesAndRedirects(): void
    {
        $typeEvenement = new TypeEvenement();
        $typeEvenement->setNom('Culte');

        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(1)->willReturn($typeEvenement);

        $em->method('getRepository')->with(TypeEvenement::class)->willReturn($repository);
        $em->expects($this->once())->method('flush');

        $controller = new AdminTypeEvenementController();
        $controller->setContainer($this->createMockContainer());

        $request = Request::create('/admin/types-evenement/1/editer', 'POST', [
            'nom' => 'Culte Dominical',
            'code' => 'CULTE'
        ]);

        $response = $controller->edit(1, $request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('Culte Dominical', $typeEvenement->getNom());
    }

    public function testDeleteTypeEvenementRemovesAndRedirects(): void
    {
        $typeEvenement = new TypeEvenement();
        $typeEvenement->setNom('Concert');

        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(1)->willReturn($typeEvenement);

        $em->method('getRepository')->with(TypeEvenement::class)->willReturn($repository);
        $em->expects($this->once())->method('remove')->with($typeEvenement);
        $em->expects($this->once())->method('flush');

        $controller = new AdminTypeEvenementController();
        $controller->setContainer($this->createMockContainer());

        $request = Request::create('/admin/types-evenement/1/supprimer', 'POST');

        $response = $controller->delete(1, $request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }
}
