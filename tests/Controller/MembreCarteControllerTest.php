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

    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $twig = $this->createMock(Environment::class);
        $controller = new MembreCarteController($twig);
        $controller->__invoke(null);
    }

    public function testFicheReturnsHtmlResponseForValidMember(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Ambohimanga');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $assoc = new Association();
        $assoc->setNom('Chorale Fararano');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(100);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean@example.com');
        $member->method('getTelephone')->willReturn('+261330011223');
        $member->method('getAdresse')->willReturn('Lot 123 Bis Analamahitsy');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1995-05-15'));
        $member->method('getAge')->willReturn(30);
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('FICHE_TOKEN_999');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'totalActivitiesCount' => 10,
            'attendedActivitiesCount' => 8,
            'participationRate' => 80.0,
            'lateCount' => 1,
            'onTimeCount' => 7,
            'lateRate' => 12.5,
            'onTimeRate' => 87.5,
            'presenceLogs' => []
        ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Jean'
                        && $args['fiangonanaNom'] === 'Paroisse Ambohimanga'
                        && $args['groupeNom'] === 'Zone Nord'
                        && $args['associationsStr'] === 'Chorale Fararano'
                        && $args['memberId'] === 100
                        && $args['token'] === 'FICHE_TOKEN_999'
                        && $args['participationStats']['participationRate'] === 80.0;
                })
            )
            ->willReturn('<html>Fiche Membre Jean Rabe</html>');

        $controller = new MembreCarteController($twig, $statsService);
        $request = Request::create('/api/membres/100/fiche');
        $response = $controller->fiche($member, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Fiche Membre Jean Rabe', $content);
    }

    public function testFicheReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Saint Jean');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(200);
        $member->method('getNom')->willReturn('Ranaivo');
        $member->method('getPrenom')->willReturn('Paul');
        $member->method('getEmail')->willReturn('paul@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getAdresse')->willReturn('Ankadifotsy');
        $member->method('getDateNaissance')->willReturn(new \DateTime('2000-01-01'));
        $member->method('getAge')->willReturn(26);
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn(null);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('PAUL_QR_TOKEN');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'totalActivitiesCount' => 12,
            'attendedActivitiesCount' => 12,
            'participationRate' => 100.0,
            'lateCount' => 0,
            'onTimeCount' => 12,
            'lateRate' => 0.0,
            'onTimeRate' => 100.0,
            'presenceLogs' => []
        ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreCarteController($twig, $statsService);

        $request = Request::create('/api/membres/200/fiche?format=json');
        $response = $controller->fiche($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(200, $data['id']);
        $this->assertEquals('Ranaivo', $data['nom']);
        $this->assertEquals('Paul', $data['prenom']);
        $this->assertEquals('Ankadifotsy', $data['adresse']);
        $this->assertEquals('01/01/2000', $data['dateNaissance']);
        $this->assertEquals(26, $data['age']);
        $this->assertEquals('Paroisse Saint Jean', $data['fiangonanaNom']);
        $this->assertEquals('PAUL_QR_TOKEN', $data['qrCodeToken']);
        $this->assertEquals(100.0, $data['participationStats']['participationRate']);
    }

    public function testFicheThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $twig = $this->createMock(Environment::class);
        $controller = new MembreCarteController($twig);
        $controller->fiche(null);
    }
}
