<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use App\Controller\ApiMemberAssociationManageController;
use App\Entity\Association;
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

class ApiMemberAssociationManageControllerTest extends TestCase
{
    public function testSaveMemberCreatesNewAssociationMember(): void
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

        $em = $this->createMock(EntityManagerInterface::class);
        $membreRepo = $this->createMock(EntityRepository::class);
        $assocRepo = $this->createMock(EntityRepository::class);
        $fiangonanaRepo = $this->createMock(EntityRepository::class);

        $membreRepo->method('findOneBy')->with(['email' => 'newmember@example.com'])->willReturn(null);
        $assoc = new Association();
        $assoc->setNom('Sampana Jeunes');
        $assocRepo->method('find')->with(2)->willReturn($assoc);

        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Test');
        $fiangonanaRepo->method('findOneBy')->willReturn($fiangonana);

        $em->method('getRepository')->willReturnCallback(function ($class) use ($membreRepo, $assocRepo, $fiangonanaRepo) {
            return match ($class) {
                Membre::class => $membreRepo,
                Association::class => $assocRepo,
                Fiangonana::class => $fiangonanaRepo,
                default => null,
            };
        });

        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $controller = new ApiMemberAssociationManageController();
        $controller->setContainer($container);

        $request = Request::create('/api/association-membres/save', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'nom' => 'Rasoa',
            'prenom' => 'Marie',
            'email' => 'newmember@example.com',
            'telephone' => '0340012345',
            'associationId' => 2,
        ]));

        $response = $controller->saveMember($request, $em);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Rasoa', $data['membre']['nom']);
        $this->assertEquals('Marie', $data['membre']['prenom']);
    }
}
