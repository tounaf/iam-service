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
        $fiangonana->setNom('Paroisse St Paul');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(21);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getAdresse')->willReturn('Lot III B 12');
        $member->method('getSexe')->willReturn('M');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('FICHE_TOKEN_123');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Jean'
                        && $args['fiangonanaNom'] === 'Paroisse St Paul'
                        && $args['groupeNom'] === 'Zone Nord'
                        && $args['memberId'] === 21
                        && $args['token'] === 'FICHE_TOKEN_123'
                        && str_contains($args['scanUrl'], '/membres/scan/FICHE_TOKEN_123');
                })
            )
            ->willReturn('<html>Jean Rabe - Fiche Membre</html>');

        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn([
                'totalActivitiesCount' => 10,
                'attendedActivitiesCount' => 8,
                'participationRate' => 80.0,
                'onTimeCount' => 7,
                'lateCount' => 1,
                'year' => (int)date('Y'),
            ]);

        $controller = new MembreFicheController($twig, $attendanceStatsService);
        $response = $controller($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Jean Rabe - Fiche Membre', $content);
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Fiangonana Ambohitra');

        $groupe = new Groupe();
        $groupe->setNom('Zone Est');

        $assoc = new Association();
        $assoc->setNom('Sampana Vangovango');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(33);
        $member->method('getNom')->willReturn('Razafy');
        $member->method('getPrenom')->willReturn('Soa');
        $member->method('getEmail')->willReturn('soa@example.com');
        $member->method('getTelephone')->willReturn('+261330099887');
        $member->method('getAdresse')->willReturn('Ankadifotsy');
        $member->method('getSexe')->willReturn('F');
        $member->method('getQrCodeToken')->willReturn('token-soa-777');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));

        $twig = $this->createMock(Environment::class);
        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->method('getMemberStats')->willReturn([
            'totalActivitiesCount' => 5,
            'attendedActivitiesCount' => 5,
            'participationRate' => 100.0,
        ]);

        $controller = new MembreFicheController($twig, $attendanceStatsService);

        $request = Request::create('/api/membres/33/fiche?format=json');
        $response = $controller($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(33, $data['id']);
        $this->assertEquals('Razafy', $data['nom']);
        $this->assertEquals('Soa', $data['prenom']);
        $this->assertEquals('F', $data['sexe']);
        $this->assertEquals('soa@example.com', $data['email']);
        $this->assertEquals('+261330099887', $data['telephone']);
        $this->assertEquals('Fiangonana Ambohitra', $data['fiangonanaNom']);
        $this->assertEquals('Zone Est', $data['groupeNom']);
        $this->assertEquals(['Sampana Vangovango'], $data['associations']);
        $this->assertEquals('token-soa-777', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertEquals(100.0, $data['stats']['participationRate']);
    }

    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $twig = $this->createMock(Environment::class);
        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $controller = new MembreFicheController($twig, $attendanceStatsService);
        $controller(null);
    }
}
