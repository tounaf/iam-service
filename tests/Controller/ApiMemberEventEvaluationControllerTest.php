<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use App\Controller\ApiMemberEventEvaluationController;
use App\Entity\Evenement;
use App\Entity\Membre;
use App\Entity\Note;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class ApiMemberEventEvaluationControllerTest extends TestCase
{
    public function testAddNoteToEventSuccess(): void
    {
        $membre = new Membre();
        $membre->setEmail('member@example.com');

        $token = $this->createMock(UsernamePasswordToken::class);
        $token->method('getUser')->willReturn($membre);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn($id) => in_array($id, ['security.token_storage', 'security.authorization_checker'], true));
        $container->method('get')->willReturnCallback(fn($id) => $id === 'security.token_storage' ? $tokenStorage : null);

        $evenement = new Evenement();
        $evenement->setNom('Chorale Spéciale');

        $em = $this->createMock(EntityManagerInterface::class);
        $eventRepo = $this->createMock(EntityRepository::class);
        $eventRepo->method('find')->with(1)->willReturn($evenement);
        $em->method('getRepository')->with(Evenement::class)->willReturn($eventRepo);

        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $controller = new ApiMemberEventEvaluationController();
        $controller->setContainer($container);

        $request = Request::create('/api/member-events/1/add-note', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'note' => 'Très dynamique !',
        ]));

        $response = $controller->addNote(1, $request, $em);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertContains('Très dynamique !', $data['notes']);
    }

    public function testUpdateCompteRenduSuccess(): void
    {
        $membre = new Membre();
        $membre->setEmail('member@example.com');

        $token = $this->createMock(UsernamePasswordToken::class);
        $token->method('getUser')->willReturn($membre);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn($id) => in_array($id, ['security.token_storage', 'security.authorization_checker'], true));
        $container->method('get')->willReturnCallback(fn($id) => $id === 'security.token_storage' ? $tokenStorage : null);

        $evenement = new Evenement();
        $evenement->setNom('Formation');

        $em = $this->createMock(EntityManagerInterface::class);
        $eventRepo = $this->createMock(EntityRepository::class);
        $eventRepo->method('find')->with(2)->willReturn($evenement);
        $em->method('getRepository')->with(Evenement::class)->willReturn($eventRepo);

        $em->expects($this->once())->method('flush');

        $controller = new ApiMemberEventEvaluationController();
        $controller->setContainer($container);

        $request = Request::create('/api/member-events/2/compte-rendu', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'compteRendu' => 'Résumé détaillé des activités.',
        ]));

        $response = $controller->updateCompteRendu(2, $request, $em);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $this->assertEquals('Résumé détaillé des activités.', $evenement->getCompteRendu());
    }
}
