<?php

namespace App\Tests\Controller;

use App\Controller\MembreFicheController;
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

class MembreFicheControllerTest extends TestCase
{
    public function testInvokeReturnsHtmlResponseForValidMember(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Ambohimanarina');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(100);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getAdresse')->willReturn('Lot III B 123');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1998-04-20'));
        $member->method('getAge')->willReturn(25);
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/rabe.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('token-fiche-100');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Jean'
                        && $args['email'] === 'jean.rabe@example.com'
                        && $args['adresse'] === 'Lot III B 123'
                        && $args['age'] === 25
                        && $args['memberId'] === 100
                        && $args['token'] === 'token-fiche-100';
                })
            )
            ->willReturn('<html>Fiche de Jean Rabe</html>');

        $controller = new MembreFicheController($twig);
        $response = $controller($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Fiche de Jean Rabe', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Isotry');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Ravelo');
        $member->method('getPrenom')->willReturn('Marie');
        $member->method('getEmail')->willReturn('marie@example.com');
        $member->method('getTelephone')->willReturn('+261330099887');
        $member->method('getAdresse')->willReturn('Antananarivo');
        $member->method('getDateNaissance')->willReturn(new \DateTime('2000-01-01'));
        $member->method('getAge')->willReturn(24);
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn(null);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('token-marie-101');

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig);

        $request = Request::create('/api/membres/101/fiche?format=json');
        $response = $controller($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(101, $data['id']);
        $this->assertEquals('Ravelo', $data['nom']);
        $this->assertEquals('Marie', $data['prenom']);
        $this->assertEquals('marie@example.com', $data['email']);
        $this->assertEquals('Paroisse Isotry', $data['fiangonanaNom']);
        $this->assertEquals('Non spécifié', $data['groupeNom']);
        $this->assertEquals('token-marie-101', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
    }

    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig);
        $controller(null);
    }
}
