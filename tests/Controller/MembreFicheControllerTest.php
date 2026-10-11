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
        $fiangonana->setNom('Paroisse Saint Jean');

        $groupe = new Groupe();
        $groupe->setNom('Zone Analamanga');

        $assoc = new Association();
        $assoc->setNom('Jeunesse Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Soa');
        $member->method('getSexe')->willReturn('M');
        $member->method('getEmail')->willReturn('soa@example.com');
        $member->method('getTelephone')->willReturn('+261320101010');
        $member->method('getAdresse')->willReturn('Lot IB 12 Antananarivo');
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/soa.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getQrCodeToken')->willReturn('TOKEN_FICHE_101');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn([
                'overallStats' => [
                    'totalEvents' => 10,
                    'attendedEvents' => 8,
                    'rate' => 80.0
                ]
            ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Soa'
                        && $args['sexe'] === 'M'
                        && $args['fiangonanaNom'] === 'Paroisse Saint Jean'
                        && $args['groupeNom'] === 'Zone Analamanga'
                        && $args['associationsStr'] === 'Jeunesse Tanora'
                        && $args['memberId'] === 101
                        && $args['token'] === 'TOKEN_FICHE_101'
                        && str_contains($args['scanUrl'], '/membres/scan/TOKEN_FICHE_101')
                        && isset($args['participationStats']);
                })
            )
            ->willReturn('<html>Fiche Membre Soa Rabe - Paroisse Saint Jean</html>');

        $controller = new MembreFicheController($twig, $statsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Soa Rabe', $content);
        $this->assertStringContainsString('Paroisse Saint Jean', $content);
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Central');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $assoc = new Association();
        $assoc->setNom('Chorale');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(50);
        $member->method('getNom')->willReturn('Rasoa');
        $member->method('getPrenom')->willReturn('Mina');
        $member->method('getSexe')->willReturn('F');
        $member->method('getEmail')->willReturn('mina@example.com');
        $member->method('getTelephone')->willReturn('+261340000111');
        $member->method('getAdresse')->willReturn('Ankorondrano');
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getQrCodeToken')->willReturn('token-mina-50');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn([
                'overallStats' => [
                    'totalEvents' => 5,
                    'attendedEvents' => 5,
                    'rate' => 100.0
                ]
            ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/50/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(50, $data['id']);
        $this->assertEquals(50, $data['memberId']);
        $this->assertEquals('Rasoa', $data['nom']);
        $this->assertEquals('Mina', $data['prenom']);
        $this->assertEquals('F', $data['sexe']);
        $this->assertEquals('mina@example.com', $data['email']);
        $this->assertEquals('+261340000111', $data['telephone']);
        $this->assertEquals('Ankorondrano', $data['adresse']);
        $this->assertEquals('Paroisse Central', $data['fiangonanaNom']);
        $this->assertEquals('Zone Nord', $data['groupeNom']);
        $this->assertEquals(['Chorale'], $data['associations']);
        $this->assertEquals('Chorale', $data['associationsStr']);
        $this->assertEquals('token-mina-50', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertEquals(100.0, $data['participationStats']['overallStats']['rate']);
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
