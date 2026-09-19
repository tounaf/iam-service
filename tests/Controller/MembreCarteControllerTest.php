<?php

namespace App\Tests\Controller;

use App\Controller\MembreCarteController;
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

class MembreCarteControllerTest extends TestCase
{
    public function testInvokeReturnsHtmlResponseForValidMemberCard(): void
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

    public function testInvokeReturnsFicheHtmlResponseForValidMember(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Fiangonana Behoririka');

        $groupe = new Groupe();
        $groupe->setNom('Zone Ankatso');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(100);
        $member->method('getNom')->willReturn('Andria');
        $member->method('getPrenom')->willReturn('Tahina');
        $member->method('getEmail')->willReturn('tahina@example.com');
        $member->method('getTelephone')->willReturn('+261331122334');
        $member->method('getAdresse')->willReturn('Lot 123 Bis Ankatso');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('TOKEN_FICHE_100');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'totalActivitiesCount' => 10,
            'attendedActivitiesCount' => 8,
            'participationRate' => 80.0,
            'lateCount' => 1,
            'onTimeCount' => 7,
        ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Andria'
                        && $args['prenom'] === 'Tahina'
                        && $args['fiangonanaNom'] === 'Fiangonana Behoririka'
                        && $args['groupeNom'] === 'Zone Ankatso'
                        && $args['stats']['participationRate'] === 80.0;
                })
            )
            ->willReturn('<html>Fiche Membre Tahina Andria - 80% participation</html>');

        $controller = new MembreCarteController($twig, $statsService);
        $request = Request::create('/api/membres/100/fiche');
        $request->attributes->set('_route', 'api_membre_fiche');

        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Fiche Membre Tahina Andria', $response->getContent());
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
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());

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

    public function testInvokeReturnsFicheJsonResponseForValidMember(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Isotry');

        $groupe = new Groupe();
        $groupe->setNom('Zone Sud');

        $assoc = new Association();
        $assoc->setNom('Chorale Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(25);
        $member->method('getNom')->willReturn('Raharison');
        $member->method('getPrenom')->willReturn('Hery');
        $member->method('getEmail')->willReturn('hery@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getAdresse')->willReturn('Lot III B Isotry');
        $member->method('getQrCodeToken')->willReturn('token-hery-25');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'totalActivitiesCount' => 12,
            'attendedActivitiesCount' => 12,
            'participationRate' => 100.0,
            'lateCount' => 0,
            'onTimeCount' => 12,
        ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreCarteController($twig, $statsService);

        $request = Request::create('/api/membres/25/fiche?format=json');
        $request->attributes->set('_route', 'api_membre_fiche');

        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(25, $data['id']);
        $this->assertEquals('Raharison', $data['nom']);
        $this->assertEquals('Hery', $data['prenom']);
        $this->assertEquals('Lot III B Isotry', $data['adresse']);
        $this->assertEquals('Paroisse Isotry', $data['fiangonanaNom']);
        $this->assertEquals('Zone Sud', $data['groupeNom']);
        $this->assertEquals(['Chorale Tanora'], $data['associations']);
        $this->assertEquals('token-hery-25', $data['qrCodeToken']);
        $this->assertEquals(100.0, $data['participationStats']['participationRate']);
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
