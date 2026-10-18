<?php

namespace App\Tests\Controller;

use App\Controller\MembreFicheController;
use App\Entity\Association;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Membre;
use App\Entity\Role;
use App\Entity\RoleAssignment;
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
        $groupe->setNom('Zone Nord');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(1);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261340000001');
        $member->method('getSexe')->willReturn('M');
        $member->method('getAdresse')->willReturn('Antananarivo 101');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1995-05-15'));
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('QR_TOKEN_999');

        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn([
                'year' => (int)date('Y'),
                'totalActivitiesCount' => 10,
                'attendedActivitiesCount' => 8,
                'participationRate' => 80.0,
                'onTimeCount' => 7,
                'lateCount' => 1,
            ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Jean'
                        && $args['fiangonanaNom'] === 'Paroisse Saint Jean'
                        && $args['groupeNom'] === 'Zone Nord'
                        && $args['memberId'] === 1
                        && $args['token'] === 'QR_TOKEN_999';
                })
            )
            ->willReturn('<html>Fiche Jean Rabe</html>');

        $controller = new MembreFicheController($twig, $attendanceStatsService);
        $request = Request::create('/api/membres/1/fiche');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Fiche Jean Rabe', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Saint Jean');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $assoc = new Association();
        $assoc->setNom('Chorale Tanora');

        $role = new Role();
        $role->setName('Président');

        $assignment = new RoleAssignment();
        $assignment->setRole($role);
        $assignment->setAssociationContext($assoc);
        $assignment->setIsActive(true);
        $assignment->setExerciceYear('2026');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(5);
        $member->method('getNom')->willReturn('Rasoa');
        $member->method('getPrenom')->willReturn('Bako');
        $member->method('getEmail')->willReturn('bako@example.com');
        $member->method('getTelephone')->willReturn('+261320000005');
        $member->method('getSexe')->willReturn('F');
        $member->method('getAdresse')->willReturn('Ankatso 101');
        $member->method('getDateNaissance')->willReturn(new \DateTime('2000-01-01'));
        $member->method('getPhotoUrl')->willReturn('bako.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection([$assignment]));
        $member->method('getQrCodeToken')->willReturn('QR_BAKO_123');

        $mockStats = [
            'year' => 2026,
            'totalActivitiesCount' => 12,
            'attendedActivitiesCount' => 12,
            'participationRate' => 100.0,
            'onTimeCount' => 12,
            'lateCount' => 0,
        ];

        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->expects($this->once())
            ->method('getMemberStats')
            ->with($member, 2026)
            ->willReturn($mockStats);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $attendanceStatsService);

        $request = Request::create('/api/membres/5/fiche?format=json&year=2026');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(5, $data['id']);
        $this->assertEquals('Rasoa', $data['nom']);
        $this->assertEquals('Bako', $data['prenom']);
        $this->assertEquals('F', $data['sexe']);
        $this->assertEquals('bako.jpg', $data['photo']);
        $this->assertEquals('Paroisse Saint Jean', $data['fiangonanaNom']);
        $this->assertEquals('Zone Nord', $data['groupeNom']);
        $this->assertEquals(['Chorale Tanora'], $data['associations']);
        $this->assertCount(1, $data['roles']);
        $this->assertEquals('PRéSIDENT', $data['roles'][0]['role']);
        $this->assertEquals('Chorale Tanora', $data['roles'][0]['context']);
        $this->assertEquals('QR_BAKO_123', $data['qrCodeToken']);
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
        $request = Request::create('/api/membres/999/fiche');
        $controller->__invoke(null, $request);
    }
}
