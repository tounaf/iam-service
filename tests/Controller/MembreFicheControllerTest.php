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
        $fiangonana->setNom('Paroisse Saint-Paul');

        $groupe = new Groupe();
        $groupe->setNom('Zone Ouest');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Ranaivo');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean@example.com');
        $member->method('getTelephone')->willReturn('+261320000001');
        $member->method('getAdresse')->willReturn('Antananarivo');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1995-05-15'));
        $member->method('getAge')->willReturn(29);
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/jean.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('MEMBER_TOKEN_999');

        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn([
                'totalActivitiesCount' => 10,
                'attendedActivitiesCount' => 8,
                'participationRate' => 80.0,
                'lateCount' => 1,
                'presenceLogs' => []
            ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Ranaivo'
                        && $args['prenom'] === 'Jean'
                        && $args['fiangonanaNom'] === 'Paroisse Saint-Paul'
                        && $args['groupeNom'] === 'Zone Ouest'
                        && $args['memberId'] === 101
                        && $args['token'] === 'MEMBER_TOKEN_999'
                        && $args['stats']['participationRate'] === 80.0
                        && str_contains($args['scanUrl'], '/membres/scan/MEMBER_TOKEN_999');
                })
            )
            ->willReturn('<html>Jean Ranaivo - Paroisse Saint-Paul - Fiche Membre</html>');

        $controller = new MembreFicheController($twig, $attendanceStatsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Jean Ranaivo', $content);
        $this->assertStringContainsString('Paroisse Saint-Paul', $content);
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Fiangonana Central');

        $groupe = new Groupe();
        $groupe->setNom('Groupe Nord');

        $assoc = new Association();
        $assoc->setNom('Chorale Sampana');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(202);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Soa');
        $member->method('getEmail')->willReturn('soa@example.com');
        $member->method('getTelephone')->willReturn('+261330000002');
        $member->method('getAdresse')->willReturn('Fianarantsoa');
        $member->method('getDateNaissance')->willReturn(new \DateTime('2000-01-01'));
        $member->method('getAge')->willReturn(24);
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getQrCodeToken')->willReturn('token-soa-202');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));

        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn([
                'totalActivitiesCount' => 5,
                'attendedActivitiesCount' => 5,
                'participationRate' => 100.0,
                'lateCount' => 0,
                'presenceLogs' => []
            ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $attendanceStatsService);

        $request = Request::create('/api/membres/202/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(202, $data['id']);
        $this->assertEquals(202, $data['memberId']);
        $this->assertEquals('Rabe', $data['nom']);
        $this->assertEquals('Soa', $data['prenom']);
        $this->assertEquals('soa@example.com', $data['email']);
        $this->assertEquals('Fiangonana Central', $data['fiangonanaNom']);
        $this->assertEquals('Groupe Nord', $data['groupeNom']);
        $this->assertEquals(['Chorale Sampana'], $data['associations']);
        $this->assertEquals('token-soa-202', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertEquals(100.0, $data['participationStats']['participationRate']);
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
