<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use App\Controller\ApiMemberEventScanController;
use App\Entity\Evenement;
use App\Entity\Membre;
use App\Entity\Presence;
use App\Entity\Association;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class ApiMemberEventScanControllerTest extends TestCase
{
    public function testCreateEventFromReactMemberSpace(): void
    {
        $membre = new Membre();
        $membre->setEmail('responsable@example.com');

        $token = $this->createMock(UsernamePasswordToken::class);
        $token->method('getUser')->willReturn($membre);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn($id) => in_array($id, ['security.token_storage', 'security.authorization_checker'], true));
        $container->method('get')->willReturnCallback(fn($id) => $id === 'security.token_storage' ? $tokenStorage : null);

        $em = $this->createMock(EntityManagerInterface::class);
        $assocRepo = $this->createMock(EntityRepository::class);
        $assoc = new Association();
        $assoc->setNom('Tanora');
        $assocRepo->method('find')->with(1)->willReturn($assoc);

        $em->method('getRepository')->willReturnCallback(function ($class) use ($assocRepo) {
            return match ($class) {
                Association::class => $assocRepo,
                default => null,
            };
        });

        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $controller = new ApiMemberEventScanController();
        $controller->setContainer($container);

        $request = Request::create('/api/member-events/create', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'nom' => 'Réunion Jeunes',
            'description' => 'Planning trimestriel',
            'lieu' => 'Temple',
            'associationId' => 1,
        ]));

        $response = $controller->createEvent($request, $em);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Réunion Jeunes', $data['evenement']['nom']);
    }

    public function testScanQrCodeRecordsPresenceForEvent(): void
    {
        $currentUser = new Membre();
        $currentUser->setEmail('officer@example.com');

        $token = $this->createMock(UsernamePasswordToken::class);
        $token->method('getUser')->willReturn($currentUser);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn($id) => in_array($id, ['security.token_storage', 'security.authorization_checker'], true));
        $container->method('get')->willReturnCallback(fn($id) => $id === 'security.token_storage' ? $tokenStorage : null);

        $evenement = new Evenement();
        $evenement->setNom('Formation des Jeunes');

        $targetMembre = new Membre();
        $targetMembre->setNom('Rakoto');
        $targetMembre->setPrenom('Jean');
        $targetMembre->setQrCodeToken('validtoken123');

        $em = $this->createMock(EntityManagerInterface::class);
        $eventRepo = $this->createMock(EntityRepository::class);
        $membreRepo = $this->createMock(EntityRepository::class);
        $presenceRepo = $this->createMock(EntityRepository::class);

        $eventRepo->method('find')->with(10)->willReturn($evenement);
        $membreRepo->method('findOneBy')->with(['qrCodeToken' => 'validtoken123'])->willReturn($targetMembre);
        $presenceRepo->method('findOneBy')->with(['membre' => $targetMembre, 'activityName' => 'Formation des Jeunes'])->willReturn(null);

        $em->method('getRepository')->willReturnCallback(function ($class) use ($eventRepo, $membreRepo, $presenceRepo) {
            return match ($class) {
                Evenement::class => $eventRepo,
                Membre::class => $membreRepo,
                Presence::class => $presenceRepo,
                default => null,
            };
        });

        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $controller = new ApiMemberEventScanController();
        $controller->setContainer($container);

        $request = Request::create('/api/member-events/10/scan', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'qrCodeToken' => 'validtoken123',
        ]));

        $response = $controller->scanQrCode(10, $request, $em);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('validée avec succès', $data['message']);
        $this->assertEquals('Jean', $data['membre']['prenom']);
    }
}
