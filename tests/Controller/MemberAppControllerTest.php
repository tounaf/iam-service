<?php

namespace App\Tests\Controller;

use App\Controller\MemberAppController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class MemberAppControllerTest extends TestCase
{
    public function testMemberAppIndexRendersReactEntrypoint(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('member_app/index.html.twig')
            ->willReturn('<html><div id="react-member-app"></div></html>');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('twig')->willReturn(true);
        $container->method('get')->with('twig')->willReturn($twig);

        $controller = new MemberAppController();
        $controller->setContainer($container);

        $response = $controller->index();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('react-member-app', $response->getContent());
    }
}
