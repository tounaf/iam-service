<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use App\Controller\ApiMemberPasswordController;
use App\Entity\Membre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class ApiMemberPasswordControllerTest extends TestCase
{
    public function testChangePasswordSuccess(): void
    {
        $membre = new Membre();
        $membre->setEmail('member@example.com');
        $membre->setPassword('old_hashed_password');

        $token = $this->createMock(UsernamePasswordToken::class);
        $token->method('getUser')->willReturn($membre);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn($id) => in_array($id, ['security.token_storage', 'security.authorization_checker'], true));
        $container->method('get')->willReturnCallback(fn($id) => $id === 'security.token_storage' ? $tokenStorage : null);

        $em = $this->createMock(EntityManagerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($membre, 'currentpass123')
            ->willReturn(true);

        $passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($membre, 'newpassword123')
            ->willReturn('new_hashed_password');

        $em->expects($this->once())->method('flush');

        $controller = new ApiMemberPasswordController();
        $controller->setContainer($container);

        $request = Request::create('/api/me/change-password', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'currentPassword' => 'currentpass123',
            'newPassword' => 'newpassword123',
        ]));

        $response = $controller->changePassword($request, $em, $passwordHasher);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Mot de passe modifié avec succès !', $data['message']);
        $this->assertEquals('new_hashed_password', $membre->getPassword());
    }
}
