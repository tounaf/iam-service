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
    private function createSampleMember(): Membre
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Fiangonana Ambohitrimanjaka');

        $groupe = new Groupe();
        $groupe->setNom('Zone Analamanga Nord');

        $assoc = new Association();
        $assoc->setNom('Chorale Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261321234567');
        $member->method('getAdresse')->willReturn('Lot IB 123 Antananarivo');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1995-05-15'));
        $member->method('getSexe')->willReturn('M');
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/jean_rabe.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getQrCodeToken')->willReturn('token-jean-rabe-101');

        return $member;
    }

    private function createSampleStats(Membre $member): array
    {
        return [
            'membre' => [
                'id' => 101,
                'nom' => 'Rabe',
                'prenom' => 'Jean',
                'email' => 'jean.rabe@example.com',
            ],
            'year' => 2026,
            'totalActivitiesCount' => 10,
            'attendedActivitiesCount' => 8,
            'participationRate' => 80.0,
            'lateCount' => 1,
            'onTimeCount' => 7,
            'lateRate' => 12.5,
            'onTimeRate' => 87.5,
            'allActivitiesInYear' => ['Culte', 'Formation des Jeunes'],
            'attendedActivitiesInYear' => ['Culte', 'Formation des Jeunes'],
            'presenceLogs' => [],
            'entityStats' => [
                'associations' => [],
                'groupe' => null,
                'fiangonana' => null,
            ],
        ];
    }

    public function testInvokeReturnsHtmlResponseForValidMember(): void
    {
        $member = $this->createSampleMember();
        $stats = $this->createSampleStats($member);

        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->expects($this->once())
            ->method('getMemberStats')
            ->with($member, 2026)
            ->willReturn($stats);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Jean'
                        && $args['fiangonanaNom'] === 'Fiangonana Ambohitrimanjaka'
                        && $args['groupeNom'] === 'Zone Analamanga Nord'
                        && $args['memberId'] === 101
                        && $args['token'] === 'token-jean-rabe-101'
                        && $args['stats']['participationRate'] === 80.0;
                })
            )
            ->willReturn('<html>Fiche Membre Jean Rabe - Fiangonana Ambohitrimanjaka</html>');

        $controller = new MembreFicheController($twig, $attendanceStatsService);
        $request = Request::create('/api/membres/101/fiche?year=2026');

        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Jean Rabe', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $member = $this->createSampleMember();
        $stats = $this->createSampleStats($member);

        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn($stats);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $attendanceStatsService);

        $request = Request::create('/api/membres/101/fiche?format=json&year=2026');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(101, $data['id']);
        $this->assertEquals('Rabe', $data['nom']);
        $this->assertEquals('Jean', $data['prenom']);
        $this->assertEquals('jean.rabe@example.com', $data['email']);
        $this->assertEquals('+261321234567', $data['telephone']);
        $this->assertEquals('Lot IB 123 Antananarivo', $data['adresse']);
        $this->assertEquals('15/05/1995', $data['dateNaissance']);
        $this->assertEquals('M', $data['sexe']);
        $this->assertEquals('Fiangonana Ambohitrimanjaka', $data['fiangonanaNom']);
        $this->assertEquals('Zone Analamanga Nord', $data['groupeNom']);
        $this->assertEquals(['Chorale Tanora'], $data['associations']);
        $this->assertEquals('token-jean-rabe-101', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertStringContainsString('/membres/scan/token-jean-rabe-101', $data['scanUrl']);
        $this->assertEquals(80.0, $data['stats']['participationRate']);
    }

    public function testInvokeThrowsNotFoundExceptionWhenMemberIsNull(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $twig = $this->createMock(Environment::class);
        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);

        $controller = new MembreFicheController($twig, $attendanceStatsService);
        $controller->__invoke(null);
    }
}
