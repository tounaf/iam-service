<?php

namespace App\Tests\Controller;

use App\Controller\MembreFicheController;
use App\Entity\Membre;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Association;
use App\Service\AttendanceStatsService;
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

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn([
                'totalActivitiesCount' => 10,
                'attendedActivitiesCount' => 8,
                'participationRate' => 80.0,
                'onTimeCount' => 7,
                'lateCount' => 1,
            ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Ratsimbazafy'
                        && $args['prenom'] === 'Nirina'
                        && $args['fiangonanaNom'] === 'Test Church'
                        && $args['groupeNom'] === 'Test Geographic Zone'
                        && $args['memberId'] === 42
                        && $args['token'] === 'SECRET_TOKEN_123'
                        && isset($args['stats']['participationRate']);
                })
            )
            ->willReturn('<html>Nirina Ratsimbazafy - Test Church</html>');

        $controller = new MembreFicheController($twig, $statsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Nirina Ratsimbazafy', $content);
        $this->assertStringContainsString('Test Church', $content);
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

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'totalActivitiesCount' => 5,
            'attendedActivitiesCount' => 5,
            'participationRate' => 100.0,
        ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/10/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(10, $data['id']);
        $this->assertEquals('Rasoa', $data['nom']);
        $this->assertEquals('Bako', $data['prenom']);
        $this->assertEquals('Paroisse Central', $data['fiangonanaNom']);
        $this->assertEquals('Zone Analamanga', $data['groupeNom']);
        $this->assertEquals(['Jeunesse KT'], $data['associations']);
        $this->assertEquals('token-123-abc', $data['qrCodeToken']);
        $this->assertEquals(100.0, $data['stats']['participationRate']);
        $this->assertNotEmpty($data['qrCodeBase64']);
    }

    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $twig = $this->createMock(Environment::class);
        $statsService = $this->createMock(AttendanceStatsService::class);
        $controller = new MembreFicheController($twig, $statsService);
        $controller->__invoke(null);
    }
}
