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
        $fiangonana->setNom('Paroisse Behoririka');

        $groupe = new Groupe();
        $groupe->setNom('Zone Analamanga');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getSexe')->willReturn('M');
        $member->method('getAdresse')->willReturn('Antananarivo');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('FICHE_TOKEN_999');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Jean'
                        && $args['fiangonanaNom'] === 'Paroisse Behoririka'
                        && $args['groupeNom'] === 'Zone Analamanga'
                        && $args['memberId'] === 101
                        && $args['token'] === 'FICHE_TOKEN_999'
                        && str_contains($args['scanUrl'], '/membres/scan/FICHE_TOKEN_999')
                        && str_contains($args['carteUrl'], '/api/membres/101/carte')
                        && isset($args['stats']);
                })
            )
            ->willReturn('<html>Fiche Membre Jean Rabe</html>');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn([
                'participationRate' => 85.5,
                'totalActivitiesCount' => 10,
                'attendedActivitiesCount' => 8,
                'lateCount' => 1,
                'onTimeCount' => 7,
                'lateRate' => 12.5,
                'onTimeRate' => 87.5,
                'presenceLogs' => [],
            ]);

        $controller = new MembreFicheController($twig, $statsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Fiche Membre Jean Rabe', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Fiangonana Central');

        $assoc = new Association();
        $assoc->setNom('Chorale Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(202);
        $member->method('getNom')->willReturn('Rasoa');
        $member->method('getPrenom')->willReturn('Marie');
        $member->method('getEmail')->willReturn('marie@example.com');
        $member->method('getTelephone')->willReturn('+261340011223');
        $member->method('getSexe')->willReturn('F');
        $member->method('getAdresse')->willReturn('Fianarantsoa');
        $member->method('getQrCodeToken')->willReturn('token-marie-202');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn(null);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));

        $twig = $this->createMock(Environment::class);
        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn([
                'participationRate' => 100.0,
                'totalActivitiesCount' => 5,
                'attendedActivitiesCount' => 5,
                'presenceLogs' => [],
            ]);

        $controller = new MembreFicheController($twig, $statsService);
        $request = Request::create('/api/membres/202/fiche?format=json&year=2026');

        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(202, $data['id']);
        $this->assertEquals(202, $data['memberId']);
        $this->assertEquals('Rasoa', $data['nom']);
        $this->assertEquals('Marie', $data['prenom']);
        $this->assertEquals('F', $data['sexe']);
        $this->assertEquals('Fiangonana Central', $data['fiangonanaNom']);
        $this->assertEquals(['Chorale Tanora'], $data['associations']);
        $this->assertEquals('token-marie-202', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertStringContainsString('/api/membres/202/carte', $data['carteUrl']);
        $this->assertStringContainsString('/api/membres/202/participation-stats?year=2026', $data['participationStatsUrl']);
        $this->assertEquals(2026, $data['year']);
        $this->assertEquals(100.0, $data['stats']['participationRate']);
    }

    public function testInvokeThrowsNotFoundExceptionWhenMemberIsNull(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $twig = $this->createMock(Environment::class);
        $statsService = $this->createMock(AttendanceStatsService::class);

        $controller = new MembreFicheController($twig, $statsService);
        $controller->__invoke(null);
    }
}
