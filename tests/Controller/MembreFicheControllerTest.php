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
        $fiangonana->setNom('Fiangonana Central');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(100);
        $member->method('getNom')->willReturn('Rasoamanana');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean@example.com');
        $member->method('getTelephone')->willReturn('+261341122334');
        $member->method('getSexe')->willReturn('M');
        $member->method('getAdresse')->willReturn('Lot 123 Antananarivo');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('MEMBER_TOKEN_100');

        $mockStats = [
            'participationRate' => 85.5,
            'totalActivitiesCount' => 20,
            'attendedActivitiesCount' => 17,
            'lateCount' => 2,
            'onTimeCount' => 15,
            'entityStats' => [],
            'presenceLogs' => [],
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
                    return $args['nom'] === 'Rasoamanana'
                        && $args['prenom'] === 'Jean'
                        && $args['fiangonanaNom'] === 'Fiangonana Central'
                        && $args['groupeNom'] === 'Zone Nord'
                        && $args['memberId'] === 100
                        && $args['token'] === 'MEMBER_TOKEN_100'
                        && $args['stats']['participationRate'] === 85.5;
                })
            )
            ->willReturn('<html>Fiche Membre Jean Rasoamanana</html>');

        $controller = new MembreFicheController($twig, $statsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Jean Rasoamanana', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Sud');

        $groupe = new Groupe();
        $groupe->setNom('Zone Est');

        $assoc = new Association();
        $assoc->setNom('KT Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(200);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Soa');
        $member->method('getEmail')->willReturn('soa@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getSexe')->willReturn('F');
        $member->method('getAdresse')->willReturn('Antsirabe');
        $member->method('getQrCodeToken')->willReturn('token-soa-200');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());

        $mockStats = [
            'participationRate' => 100.0,
            'totalActivitiesCount' => 10,
            'attendedActivitiesCount' => 10,
            'lateCount' => 0,
            'onTimeCount' => 10,
            'entityStats' => [],
            'presenceLogs' => [],
        ];

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn($mockStats);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/200/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(200, $data['id']);
        $this->assertEquals(200, $data['memberId']);
        $this->assertEquals('Rabe', $data['nom']);
        $this->assertEquals('Soa', $data['prenom']);
        $this->assertEquals('soa@example.com', $data['email']);
        $this->assertEquals('Paroisse Sud', $data['fiangonanaNom']);
        $this->assertEquals('Zone Est', $data['groupeNom']);
        $this->assertEquals(['KT Tanora'], $data['associations']);
        $this->assertEquals('token-soa-200', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertEquals(100.0, $data['stats']['participationRate']);
    }

    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $twig = $this->createMock(Environment::class);

        $controller = new MembreFicheController($twig, $statsService);
        $controller->__invoke(null);
    }
}
