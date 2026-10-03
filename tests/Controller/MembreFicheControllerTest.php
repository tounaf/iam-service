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
        $fiangonana->setNom('Fiangonana Ambohitrimanjaka');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $assoc = new Association();
        $assoc->setNom('STK Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getAdresse')->willReturn('Lot III B Antananarivo');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1998-05-15'));
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getQrCodeToken')->willReturn('QR_JEAN_101');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->expects($this->once())
            ->method('getMemberStats')
            ->with($member, (int)date('Y'))
            ->willReturn([
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
                        && $args['fiangonanaNom'] === 'Fiangonana Ambohitrimanjaka'
                        && $args['groupeNom'] === 'Zone Nord'
                        && $args['associationsStr'] === 'STK Tanora'
                        && $args['memberId'] === 101
                        && $args['token'] === 'QR_JEAN_101'
                        && $args['stats']['participationRate'] === 80.0;
                })
            )
            ->willReturn('<html>Fiche Membre Jean Rabe - 80%</html>');

        $controller = new MembreFicheController($twig, $statsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Jean Rabe', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenRequested(): void
    {
        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(202);
        $member->method('getNom')->willReturn('Rasoa');
        $member->method('getPrenom')->willReturn('Marie');
        $member->method('getEmail')->willReturn('marie@example.com');
        $member->method('getTelephone')->willReturn('+261340011223');
        $member->method('getAdresse')->willReturn('Lot IV X');
        $member->method('getDateNaissance')->willReturn(null);
        $member->method('getFiangonana')->willReturn(null);
        $member->method('getZoneGeographique')->willReturn(null);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('TOKEN_MARIE_202');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')
            ->willReturn([
                'totalActivitiesCount' => 5,
                'attendedActivitiesCount' => 5,
                'participationRate' => 100.0,
                'lateCount' => 0,
                'onTimeCount' => 5,
                'lateRate' => 0.0,
                'onTimeRate' => 100.0,
                'presenceLogs' => []
            ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/202/fiche?format=json&year=2024');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(202, $data['id']);
        $this->assertEquals('Rasoa', $data['nom']);
        $this->assertEquals('Marie', $data['prenom']);
        $this->assertEquals('TOKEN_MARIE_202', $data['qrCodeToken']);
        $this->assertEquals(100.0, $data['statistics']['participationRate']);
        $this->assertNotEmpty($data['qrCodeBase64']);
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
