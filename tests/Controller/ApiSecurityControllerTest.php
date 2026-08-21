<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use App\Controller\ApiSecurityController;
use App\Entity\Membre;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class ApiSecurityControllerTest extends TestCase
{
    public function testLoginWithUserReturnsUserData(): void
    {
        $membre = new Membre();
        $membre->setEmail('test@example.com');
        $membre->setNom('Ratsimba');
        $membre->setPrenom('Jean');

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

        $controller = new ApiSecurityController();
        $controller->setContainer($container);

        $response = $controller->login();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('test@example.com', $data['email']);
        $this->assertEquals('Ratsimba', $data['nom']);
    }

    public function testMeReturnsUnauthorizedWhenNotLoggedIn(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn($id) => in_array($id, ['security.token_storage', 'security.authorization_checker'], true));
        $container->method('get')->willReturnCallback(function ($id) use ($tokenStorage) {
            if ($id === 'security.token_storage') return $tokenStorage;
            return null;
        });

        $controller = new ApiSecurityController();
        $controller->setContainer($container);

        $response = $controller->me();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
