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
        $fiangonana->setNom('Paroisse Ambohimanarina');

        $groupe = new Groupe();
        $groupe->setNom('Zone Analamanga');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(100);
        $member->method('getNom')->willReturn('Rakotondrabe');
        $member->method('getPrenom')->willReturn('Hery');
        $member->method('getEmail')->willReturn('hery@example.com');
        $member->method('getTelephone')->willReturn('+261320102030');
        $member->method('getSexe')->willReturn('M');
        $member->method('getAdresse')->willReturn('Lot IB 42 Antananarivo');
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/hery.jpg');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1995-05-15'));
        $member->method('getAge')->willReturn(31);
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('FICHE_TOKEN_999');

        $mockStats = [
            'totalActivitiesCount' => 10,
            'attendedActivitiesCount' => 8,
            'participationRate' => 80.0,
            'lateCount' => 1,
            'onTimeCount' => 7,
            'entityStats' => [
                'associations' => [],
                'groupe' => null,
                'fiangonana' => null,
            ],
        ];

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn($mockStats);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rakotondrabe'
                        && $args['prenom'] === 'Hery'
                        && $args['fiangonanaNom'] === 'Paroisse Ambohimanarina'
                        && $args['groupeNom'] === 'Zone Analamanga'
                        && $args['memberId'] === 100
                        && $args['token'] === 'FICHE_TOKEN_999'
                        && $args['stats']['participationRate'] === 80.0;
                })
            )
            ->willReturn('<html>Fiche Hery Rakotondrabe - 80% Participation</html>');

        $controller = new MembreFicheController($twig, $statsService);
        $request = Request::create('/api/membres/100/fiche');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Hery Rakotondrabe', $content);
        $this->assertStringContainsString('80% Participation', $content);
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Central');

        $groupe = new Groupe();
        $groupe->setNom('Zone Est');

        $assoc = new Association();
        $assoc->setNom('Chorale Farafara');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(25);
        $member->method('getNom')->willReturn('Rasoamanarivo');
        $member->method('getPrenom')->willReturn('Mialy');
        $member->method('getEmail')->willReturn('mialy@example.com');
        $member->method('getTelephone')->willReturn('+261340102030');
        $member->method('getSexe')->willReturn('F');
        $member->method('getAdresse')->willReturn('Soarano Antananarivo');
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getDateNaissance')->willReturn(new \DateTime('2000-10-10'));
        $member->method('getAge')->willReturn(26);
        $member->method('getQrCodeToken')->willReturn('token-mialy-123');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));

        $mockStats = [
            'totalActivitiesCount' => 5,
            'attendedActivitiesCount' => 5,
            'participationRate' => 100.0,
            'lateCount' => 0,
            'onTimeCount' => 5,
        ];

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn($mockStats);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/25/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(25, $data['id']);
        $this->assertEquals('Rasoamanarivo', $data['nom']);
        $this->assertEquals('Mialy', $data['prenom']);
        $this->assertEquals('mialy@example.com', $data['email']);
        $this->assertEquals('F', $data['sexe']);
        $this->assertEquals('Paroisse Central', $data['fiangonanaNom']);
        $this->assertEquals('Zone Est', $data['groupeNom']);
        $this->assertEquals(['Chorale Farafara'], $data['associations']);
        $this->assertEquals('token-mialy-123', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertEquals(100.0, $data['participationStats']['participationRate']);
    }

    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $twig = $this->createMock(Environment::class);
        $statsService = $this->createMock(AttendanceStatsService::class);
        $controller = new MembreFicheController($twig, $statsService);
        $request = Request::create('/api/membres/999/fiche');
        $controller->__invoke(null, $request);
    }
}
