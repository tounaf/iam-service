<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use App\Controller\MembreProfileUpdateController;
use App\Entity\Fiangonana;
use App\Entity\Membre;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class MembreProfileUpdateControllerTest extends TestCase
{
    public function testUpdateProfileSuccess(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Test');

        $membre = new Membre();
        $membre->setEmail('old@example.com');
        $membre->setNom('OldNom');
        $membre->setPrenom('OldPrenom');
        $membre->setFiangonana($fiangonana);

        // Reflection to set ID = 1
        $ref = new \ReflectionProperty(Membre::class, 'id');
        $ref->setValue($membre, 1);

        $token = $this->createMock(UsernamePasswordToken::class);
        $token->method('getUser')->willReturn($membre);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn($id) => in_array($id, ['security.token_storage', 'security.authorization_checker'], true));
        $container->method('get')->willReturnCallback(function ($id) use ($tokenStorage) {
            if ($id === 'security.token_storage') return $tokenStorage;
            return null;
        });

        $em = $this->createMock(EntityManagerInterface::class);
        $membreRepo = $this->createMock(EntityRepository::class);
        $membreRepo->method('find')->with(1)->willReturn($membre);
        $em->method('getRepository')->with(Membre::class)->willReturn($membreRepo);
        $em->expects($this->once())->method('flush');

        $controller = new MembreProfileUpdateController();
        $controller->setContainer($container);

        $request = Request::create('/api/membres/1/update-profile', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'nom' => 'NewNom',
            'prenom' => 'NewPrenom',
            'email' => 'new@example.com',
            'telephone' => '0340011223',
            'adresse' => 'Lot II M 45 Antananarivo',
        ]));

        $response = $controller->updateProfile(1, $request, $em);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $this->assertEquals('NewNom', $membre->getNom());
        $this->assertEquals('NewPrenom', $membre->getPrenom());
        $this->assertEquals('new@example.com', $membre->getEmail());
        $this->assertEquals('0340011223', $membre->getTelephone());
        $this->assertEquals('Lot II M 45 Antananarivo', $membre->getAdresse());
    }
}
