<?php

namespace App\Tests\Controller;

use App\Controller\MembreCarteController;
use App\Entity\Membre;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

class MembreCarteControllerTest extends TestCase
{
    public function testInvokeReturnsHtmlResponseForValidMember(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Test Church');

        $groupe = new Groupe();
        $groupe->setNom('Test Geographic Zone');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(42);
        $member->method('getNom')->willReturn('Ratsimbazafy');
        $member->method('getPrenom')->willReturn('Nirina');
        $member->method('getEmail')->willReturn('nirina@example.com');
        $member->method('getTelephone')->willReturn('+261320000000');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/carte.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Ratsimbazafy'
                        && $args['prenom'] === 'Nirina'
                        && $args['fiangonanaNom'] === 'Test Church'
                        && $args['groupeNom'] === 'Test Geographic Zone'
                        && $args['memberId'] === 42;
                })
            )
            ->willReturn('<html>Nirina Ratsimbazafy - Test Church - Test Geographic Zone - /api/membres/42/qr-code</html>');

        $controller = new MembreCarteController($twig);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Nirina Ratsimbazafy', $content);
        $this->assertStringContainsString('Test Church', $content);
        $this->assertStringContainsString('Test Geographic Zone', $content);
    }

    public function testInvokeReturnsJsonResponseWhenFormatJsonQueryParamProvided(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Test Church');

        $groupe = new Groupe();
        $groupe->setNom('Test Geographic Zone');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(42);
        $member->method('getNom')->willReturn('Ratsimbazafy');
        $member->method('getPrenom')->willReturn('Nirina');
        $member->method('getEmail')->willReturn('nirina@example.com');
        $member->method('getTelephone')->willReturn('+261320000000');
        $member->method('getQrCodeToken')->willReturn('token_abc123');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $request = new Request(['format' => 'json']);
        $controller = new MembreCarteController($twig);
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(42, $data['memberId']);
        $this->assertEquals('Ratsimbazafy', $data['nom']);
        $this->assertEquals('Nirina', $data['prenom']);
        $this->assertEquals('Test Church', $data['fiangonanaNom']);
        $this->assertEquals('Test Geographic Zone', $data['groupeNom']);
        $this->assertEquals('token_abc123', $data['qrCodeToken']);
        $this->assertEquals('/api/membres/42/qr-code', $data['qrCodeUrl']);
        $this->assertStringContainsString('/membres/scan/token_abc123', $data['scanUrl']);
    }

    public function testInvokeReturnsJsonResponseWhenAcceptHeaderIsJson(): void
    {
        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(10);
        $member->method('getNom')->willReturn('Doe');
        $member->method('getPrenom')->willReturn('John');
        $member->method('getQrCodeToken')->willReturn('token_xyz');
        $member->method('getAssociations')->willReturn(new ArrayCollection());

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $request = new Request();
        $request->headers->set('Accept', 'application/json');

        $controller = new MembreCarteController($twig);
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(10, $data['memberId']);
        $this->assertEquals('Doe', $data['nom']);
        $this->assertEquals('John', $data['prenom']);
    }

    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $twig = $this->createMock(Environment::class);
        $controller = new MembreCarteController($twig);
        $controller->__invoke(null);
    }
}
