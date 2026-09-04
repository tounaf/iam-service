<?php

namespace App\Tests\Controller;

use App\Controller\MembreCarteController;
use App\Entity\Membre;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Association;
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
        $member->method('getQrCodeToken')->willReturn('SECRET_TOKEN_123');

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
                        && $args['memberId'] === 42
                        && $args['token'] === 'SECRET_TOKEN_123'
                        && str_contains($args['scanUrl'], '/membres/scan/SECRET_TOKEN_123');
                })
            )
            ->willReturn('<html>Nirina Ratsimbazafy - Test Church - Test Geographic Zone</html>');

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

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Central');

        $groupe = new Groupe();
        $groupe->setNom('Zone Analamanga');

        $assoc = new Association();
        $assoc->setNom('Jeunesse KT');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(10);
        $member->method('getNom')->willReturn('Rasoa');
        $member->method('getPrenom')->willReturn('Bako');
        $member->method('getEmail')->willReturn('bako@example.com');
        $member->method('getTelephone')->willReturn('+261340000000');
        $member->method('getQrCodeToken')->willReturn('token-123-abc');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));

        $twig = $this->createMock(Environment::class);
        $controller = new MembreCarteController($twig);

        $request = Request::create('/api/membres/10/carte?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(10, $data['id']);
        $this->assertEquals('Rasoa', $data['nom']);
        $this->assertEquals('Bako', $data['prenom']);
        $this->assertEquals('bako@example.com', $data['email']);
        $this->assertEquals('+261340000000', $data['telephone']);
        $this->assertEquals('Paroisse Central', $data['fiangonanaNom']);
        $this->assertEquals('Zone Analamanga', $data['groupeNom']);
        $this->assertEquals(['Jeunesse KT'], $data['associations']);
        $this->assertEquals('token-123-abc', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
    }

    public function testInvokeReturnsJsonResponseWithCompleteMemberDetails(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Fiangonana Fenoarivo');

        $groupe = new Groupe();
        $groupe->setNom('Zone Ouest');

        $assoc = new Association();
        $assoc->setNom('Sampana Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(15);
        $member->method('getNom')->willReturn('Rakoto');
        $member->method('getPrenom')->willReturn('Koto');
        $member->method('getEmail')->willReturn('koto@example.com');
        $member->method('getTelephone')->willReturn('+261321122334');
        $member->method('getQrCodeToken')->willReturn('token-koto-888');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));

        $twig = $this->createMock(Environment::class);
        $controller = new MembreCarteController($twig);

        $request = Request::create('/api/membres/15/carte?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(15, $data['id']);
        $this->assertEquals(15, $data['memberId']);
        $this->assertEquals('Rakoto', $data['nom']);
        $this->assertEquals('Koto', $data['prenom']);
        $this->assertEquals('Fiangonana Fenoarivo', $data['fiangonanaNom']);
        $this->assertEquals('Zone Ouest', $data['groupeNom']);
        $this->assertArrayHasKey('associationsArray', $data);
        $this->assertEquals('Sampana Tanora', $data['associationsStr']);
        $this->assertStringContainsString('/membres/scan/token-koto-888', $data['scanUrl']);
        $this->assertNotEmpty($data['qrCodeBase64']);
    }

    public function testInvokeFicheEndpointReturnsJsonResponseWhenRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Sud');

        $groupe = new Groupe();
        $groupe->setNom('Zone Est');

        $assoc = new Association();
        $assoc->setNom('Mpanazava');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(20);
        $member->method('getNom')->willReturn('Andria');
        $member->method('getPrenom')->willReturn('Soa');
        $member->method('getEmail')->willReturn('soa@example.com');
        $member->method('getTelephone')->willReturn('+261330011223');
        $member->method('getQrCodeToken')->willReturn('token-fiche-20');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));

        $twig = $this->createMock(Environment::class);
        $controller = new MembreCarteController($twig);

        $request = Request::create('/api/membres/20/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(20, $data['id']);
        $this->assertEquals('Andria', $data['nom']);
        $this->assertEquals('Soa', $data['prenom']);
        $this->assertEquals('Paroisse Sud', $data['fiangonanaNom']);
        $this->assertEquals('token-fiche-20', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
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
