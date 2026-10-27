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
        $fiangonana->setNom('Fiangonana Antananarivo');

        $groupe = new Groupe();
        $groupe->setNom('Zone Centre');

        $assoc = new Association();
        $assoc->setNom('Chorale Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261331234567');
        $member->method('getAdresse')->willReturn('Lot 123 Bis Antananarivo');
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/jean.jpg');
        $member->method('getSexe')->willReturn('M');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getQrCodeToken')->willReturn('FICHE_TOKEN_999');

        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->method('getMemberStats')
            ->willReturn([
                'participationRate' => 85.5,
                'attendedActivitiesCount' => 17,
                'totalActivitiesCount' => 20,
                'onTimeCount' => 15,
                'lateCount' => 2,
                'presenceLogs' => [],
            ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Jean'
                        && $args['fiangonanaNom'] === 'Fiangonana Antananarivo'
                        && $args['groupeNom'] === 'Zone Centre'
                        && $args['memberId'] === 101
                        && $args['token'] === 'FICHE_TOKEN_999'
                        && isset($args['stats']['participationRate'])
                        && $args['stats']['participationRate'] === 85.5;
                })
            )
            ->willReturn('<html>Fiche Membre Jean Rabe</html>');

        $controller = new MembreFicheController($twig, $attendanceStatsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Fiche Membre Jean Rabe', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Fiangonana Sambava');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(202);
        $member->method('getNom')->willReturn('Rasoamanarivo');
        $member->method('getPrenom')->willReturn('Marie');
        $member->method('getEmail')->willReturn('marie@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getAdresse')->willReturn('Sambava Centre');
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getSexe')->willReturn('F');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('token-marie-777');

        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->method('getMemberStats')
            ->willReturn([
                'participationRate' => 100.0,
                'attendedActivitiesCount' => 5,
                'totalActivitiesCount' => 5,
                'onTimeCount' => 5,
                'lateCount' => 0,
            ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $attendanceStatsService);

        $request = Request::create('/api/membres/202/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(202, $data['id']);
        $this->assertEquals('Rasoamanarivo', $data['nom']);
        $this->assertEquals('Marie', $data['prenom']);
        $this->assertEquals('Fiangonana Sambava', $data['fiangonanaNom']);
        $this->assertEquals('Zone Nord', $data['groupeNom']);
        $this->assertEquals('token-marie-777', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertEquals(100.0, $data['stats']['participationRate']);
    }

    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig);
        $controller->__invoke(null);
    }
}
