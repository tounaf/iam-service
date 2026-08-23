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

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
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
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/photo.jpg');
        $member->method('getQrCodeToken')->willReturn('token_abc123');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());

        $twig = $this->createMock(Environment::class);
        $controller = new MembreCarteController($twig);

        $request = new Request(['format' => 'json']);
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(42, $data['id']);
        $this->assertEquals('Ratsimbazafy', $data['nom']);
        $this->assertEquals('Nirina', $data['prenom']);
        $this->assertEquals('token_abc123', $data['qrCodeToken']);
        $this->assertEquals('Test Church', $data['fiangonana']['nom']);
        $this->assertEquals('Test Geographic Zone', $data['zoneGeographique']['nom']);
        $this->assertArrayHasKey('qrCodeBase64', $data);
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
