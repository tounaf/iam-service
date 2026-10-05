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
        $fiangonana->setNom('Test Church');

        $groupe = new Groupe();
        $groupe->setNom('Test Zone');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Randria');
        $member->method('getPrenom')->willReturn('Paul');
        $member->method('getEmail')->willReturn('paul@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getAdresse')->willReturn('Antananarivo');
        $member->method('getSexe')->willReturn('M');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('FICHE_TOKEN_123');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn([
                'participationRate' => 85.5,
                'totalActivitiesCount' => 10,
                'attendedActivitiesCount' => 8,
                'onTimeCount' => 7,
                'lateCount' => 1,
            ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Randria'
                        && $args['prenom'] === 'Paul'
                        && $args['fiangonanaNom'] === 'Test Church'
                        && $args['groupeNom'] === 'Test Zone'
                        && $args['memberId'] === 101
                        && $args['token'] === 'FICHE_TOKEN_123'
                        && $args['participationStats']['participationRate'] === 85.5;
                })
            )
            ->willReturn('<html>Fiche Membre Paul Randria</html>');

        $controller = new MembreFicheController($twig, $statsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Fiche Membre Paul Randria', $content);
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Isotry');

        $groupe = new Groupe();
        $groupe->setNom('Zone Centre');

        $assoc = new Association();
        $assoc->setNom('Chorale Vatsy');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(202);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean@example.com');
        $member->method('getTelephone')->willReturn('+261330099887');
        $member->method('getAdresse')->willReturn('Isotry 101');
        $member->method('getSexe')->willReturn('M');
        $member->method('getQrCodeToken')->willReturn('token-fiche-202');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn([
                'participationRate' => 100.0,
                'totalActivitiesCount' => 5,
                'attendedActivitiesCount' => 5,
            ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/202/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(202, $data['id']);
        $this->assertEquals('Rabe', $data['nom']);
        $this->assertEquals('Jean', $data['prenom']);
        $this->assertEquals('jean@example.com', $data['email']);
        $this->assertEquals('Paroisse Isotry', $data['fiangonanaNom']);
        $this->assertEquals('Zone Centre', $data['groupeNom']);
        $this->assertEquals(['Chorale Vatsy'], $data['associations']);
        $this->assertEquals('token-fiche-202', $data['qrCodeToken']);
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
