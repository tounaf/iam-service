<?php

namespace App\Tests\Controller;

use App\Controller\MembreFicheController;
use App\Entity\Association;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Membre;
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
        $fiangonana->setNom('Test Parish Church');

        $groupe = new Groupe();
        $groupe->setNom('Test Geographic Zone');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(100);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261320000100');
        $member->method('getAdresse')->willReturn('Lot II M 12 Antananarivo');
        $member->method('getSexe')->willReturn('M');
        $member->method('getAge')->willReturn(30);
        $member->method('getDateNaissance')->willReturn(new \DateTime('1996-05-15'));
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/jean.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('SECRET_FICHE_TOKEN');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'participationRate' => 85.5,
            'totalActivitiesCount' => 20,
            'attendedActivitiesCount' => 17,
            'lateCount' => 2,
            'onTimeCount' => 15,
            'lateRate' => 11.76,
            'onTimeRate' => 88.24,
            'entityStats' => [
                'associations' => [],
                'groupe' => null,
                'fiangonana' => null,
            ]
        ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Jean'
                        && $args['fiangonanaNom'] === 'Test Parish Church'
                        && $args['groupeNom'] === 'Test Geographic Zone'
                        && $args['memberId'] === 100
                        && $args['token'] === 'SECRET_FICHE_TOKEN'
                        && $args['stats']['participationRate'] === 85.5;
                })
            )
            ->willReturn('<html>Fiche Jean Rabe</html>');

        $controller = new MembreFicheController($twig, $statsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Fiche Jean Rabe', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Fiangonana Faravohitra');

        $groupe = new Groupe();
        $groupe->setNom('Zone Est');

        $assoc = new Association();
        $assoc->setNom('Sampana Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(200);
        $member->method('getNom')->willReturn('Ranaivo');
        $member->method('getPrenom')->willReturn('Paul');
        $member->method('getEmail')->willReturn('paul@example.com');
        $member->method('getTelephone')->willReturn('+261340000200');
        $member->method('getAdresse')->willReturn('Lot III A Faravohitra');
        $member->method('getSexe')->willReturn('M');
        $member->method('getAge')->willReturn(25);
        $member->method('getDateNaissance')->willReturn(new \DateTime('2001-01-01'));
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getQrCodeToken')->willReturn('token-paul-200');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));

        $mockStats = [
            'participationRate' => 90.0,
            'totalActivitiesCount' => 10,
            'attendedActivitiesCount' => 9,
            'lateCount' => 1,
            'onTimeCount' => 8,
            'lateRate' => 11.11,
            'onTimeRate' => 88.89,
            'entityStats' => [
                'associations' => [
                    [
                        'id' => 1,
                        'nom' => 'Sampana Tanora',
                        'totalEvents' => 5,
                        'attended' => 5,
                        'presenceRate' => 100.0,
                    ]
                ],
                'groupe' => null,
                'fiangonana' => null,
            ]
        ];

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn($mockStats);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/200/fiche?format=json&year=2026');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(200, $data['id']);
        $this->assertEquals(200, $data['memberId']);
        $this->assertEquals('Ranaivo', $data['nom']);
        $this->assertEquals('Paul', $data['prenom']);
        $this->assertEquals('paul@example.com', $data['email']);
        $this->assertEquals('+261340000200', $data['telephone']);
        $this->assertEquals('Fiangonana Faravohitra', $data['fiangonanaNom']);
        $this->assertEquals('Zone Est', $data['groupeNom']);
        $this->assertEquals(['Sampana Tanora'], $data['associations']);
        $this->assertEquals('token-paul-200', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertStringContainsString('/membres/scan/token-paul-200', $data['scanUrl']);
        $this->assertEquals('/api/membres/200/carte', $data['carteUrl']);
        $this->assertStringContainsString('/api/membres/200/participation-stats?year=2026', $data['participationStatsUrl']);
        $this->assertEquals(90.0, $data['attendanceStats']['participationRate']);
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
