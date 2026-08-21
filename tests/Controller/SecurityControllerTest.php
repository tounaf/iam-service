<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use App\Controller\SecurityController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;

class SecurityControllerTest extends TestCase
{
    public function testLoginRendersLoginPage(): void
    {
        $authUtils = $this->createMock(AuthenticationUtils::class);
        $authUtils->method('getLastAuthenticationError')->willReturn(null);
        $authUtils->method('getLastUsername')->willReturn('test@example.com');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('security/login.html.twig', [
                'last_username' => 'test@example.com',
                'error' => null,
            ])
            ->willReturn('<html>Login Page</html>');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn($id) => in_array($id, ['twig', 'security.helper'], true));
        $container->method('get')->willReturnCallback(function ($id) use ($twig) {
            if ($id === 'twig') return $twig;
            if ($id === 'security.helper') {
                $helper = $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class);
                $helper->method('getUser')->willReturn(null);
                return $helper;
            }
            return null;
        });

        $controller = new SecurityController();
        $controller->setContainer($container);

        $response = $controller->login($authUtils);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('<html>Login Page</html>', $response->getContent());
    }
}
