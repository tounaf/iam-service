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
        $fiangonana->setNom('Paroisse Ambohitrimanjaka');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(100);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261340011223');
        $member->method('getAdresse')->willReturn('Lot IB 12 Antananarivo');
        $member->method('getSexe')->willReturn('M');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1995-05-15'));
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/jean.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('MEMBER_TOKEN_999');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn([
                'participationRate' => 85.5,
                'totalActivitiesCount' => 20,
                'attendedActivitiesCount' => 17,
                'lateCount' => 2,
                'onTimeCount' => 15,
            ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Jean'
                        && $args['fiangonanaNom'] === 'Paroisse Ambohitrimanjaka'
                        && $args['groupeNom'] === 'Zone Nord'
                        && $args['memberId'] === 100
                        && $args['token'] === 'MEMBER_TOKEN_999'
                        && $args['stats']['participationRate'] === 85.5
                        && str_contains($args['scanUrl'], '/membres/scan/MEMBER_TOKEN_999');
                })
            )
            ->willReturn('<html>Jean Rabe Fiche Membre</html>');

        $controller = new MembreFicheController($twig, $statsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Jean Rabe Fiche Membre', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Isotry');

        $assoc = new Association();
        $assoc->setNom('Chorale Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(200);
        $member->method('getNom')->willReturn('Rasoa');
        $member->method('getPrenom')->willReturn('Marie');
        $member->method('getEmail')->willReturn('marie@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getAdresse')->willReturn('Lot VB 45');
        $member->method('getSexe')->willReturn('F');
        $member->method('getDateNaissance')->willReturn(new \DateTime('2000-01-01'));
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn(null);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('token-marie-200');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'participationRate' => 100.0,
            'totalActivitiesCount' => 10,
            'attendedActivitiesCount' => 10,
        ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/200/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(200, $data['id']);
        $this->assertEquals('Rasoa', $data['nom']);
        $this->assertEquals('Marie', $data['prenom']);
        $this->assertEquals('Paroisse Isotry', $data['fiangonanaNom']);
        $this->assertEquals('Non spécifié', $data['groupeNom']);
        $this->assertEquals(['Chorale Tanora'], $data['associations']);
        $this->assertEquals('token-marie-200', $data['qrCodeToken']);
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

        $controller->__invoke(null);
    }
}
