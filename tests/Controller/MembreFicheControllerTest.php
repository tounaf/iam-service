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
        $fiangonana->setNom('Fiangonana Central');

        $groupe = new Groupe();
        $groupe->setNom('Groupe Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(100);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Soa');
        $member->method('getEmail')->willReturn('soa@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getAdresse')->willReturn('Lot III X Antananarivo');
        $member->method('getDateNaissance')->willReturn(new \DateTimeImmutable('1998-05-15'));
        $member->method('getAge')->willReturn(26);
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('SECRET_FICHE_TOKEN');

        $stats = [
            'participationRate' => 85.5,
            'attendedActivitiesCount' => 17,
            'totalActivitiesCount' => 20,
            'lateCount' => 2,
            'onTimeCount' => 15,
            'entityStats' => [
                'associations' => [],
                'groupe' => null,
                'fiangonana' => null,
            ]
        ];

        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->expects($this->once())
            ->method('getMemberStats')
            ->with($member, (int) date('Y'))
            ->willReturn($stats);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Soa'
                        && $args['fiangonanaNom'] === 'Fiangonana Central'
                        && $args['memberId'] === 100
                        && $args['token'] === 'SECRET_FICHE_TOKEN'
                        && $args['stats']['participationRate'] === 85.5;
                })
            )
            ->willReturn('<html>Fiche Soa Rabe</html>');

        $controller = new MembreFicheController($twig, $attendanceStatsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Fiche Soa Rabe', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Ambohimanarina');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $assoc = new Association();
        $assoc->setNom('KTLM');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(200);
        $member->method('getNom')->willReturn('Rasoamanarivo');
        $member->method('getPrenom')->willReturn('Mialy');
        $member->method('getEmail')->willReturn('mialy@example.com');
        $member->method('getTelephone')->willReturn('+261340011223');
        $member->method('getAdresse')->willReturn('Ambohimanarina');
        $member->method('getDateNaissance')->willReturn(new \DateTimeImmutable('2000-01-01'));
        $member->method('getAge')->willReturn(24);
        $member->method('getQrCodeToken')->willReturn('token-mialy-456');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));

        $stats = [
            'participationRate' => 90.0,
            'attendedActivitiesCount' => 18,
            'totalActivitiesCount' => 20,
            'lateCount' => 1,
            'onTimeCount' => 17,
            'entityStats' => [
                'associations' => [['id' => 1, 'nom' => 'KTLM', 'presenceRate' => 90.0]],
                'groupe' => null,
                'fiangonana' => null,
            ]
        ];

        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->method('getMemberStats')->willReturn($stats);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $attendanceStatsService);

        $request = Request::create('/api/membres/200/fiche?format=json&year=2024');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(200, $data['id']);
        $this->assertEquals('Rasoamanarivo', $data['nom']);
        $this->assertEquals('Mialy', $data['prenom']);
        $this->assertEquals('mialy@example.com', $data['email']);
        $this->assertEquals('Paroisse Ambohimanarina', $data['fiangonanaNom']);
        $this->assertEquals('Zone Nord', $data['groupeNom']);
        $this->assertEquals(['KTLM'], $data['associations']);
        $this->assertEquals('token-mialy-456', $data['qrCodeToken']);
        $this->assertEquals(2024, $data['year']);
        $this->assertEquals(90.0, $data['stats']['participationRate']);
        $this->assertNotEmpty($data['qrCodeBase64']);
    }

    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $twig = $this->createMock(Environment::class);
        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);

        $controller = new MembreFicheController($twig, $attendanceStatsService);
        $controller->__invoke(null);
    }
}
