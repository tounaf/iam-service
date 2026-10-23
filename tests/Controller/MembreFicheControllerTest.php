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
        $fiangonana->setNom('Paroisse Ambohitrimanjaka');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $assoc = new Association();
        $assoc->setNom('KTLM');

        $role = new Role();
        $role->setName('Responsable');

        $roleAssignment = new RoleAssignment();
        $roleAssignment->setRole($role);
        $roleAssignment->setAssociationContext($assoc);
        $roleAssignment->setExerciceYear(2025);

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261341234567');
        $member->method('getAdresse')->willReturn('Lot IB 42 Antananarivo');
        $member->method('getSexe')->willReturn('M');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1995-06-15'));
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/jean.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection([$roleAssignment]));
        $member->method('getQrCodeToken')->willReturn('QR_TOKEN_JEAN_101');

        $dummyStats = [
            'participationRate' => 85.5,
            'totalActivitiesCount' => 20,
            'attendedActivitiesCount' => 17,
            'onTimeCount' => 15,
            'lateCount' => 2,
            'entityStats' => [
                'associations' => [],
                'groupe' => null,
                'fiangonana' => null,
            ]
        ];

        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->expects($this->once())
            ->method('getMemberStats')
            ->with($member, (int)date('Y'))
            ->willReturn($dummyStats);

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
                        && $args['associationsStr'] === 'KTLM'
                        && $args['memberId'] === 101
                        && $args['token'] === 'QR_TOKEN_JEAN_101'
                        && $args['sexeLabel'] === 'Homme'
                        && $args['stats']['participationRate'] === 85.5;
                })
            )
            ->willReturn('<html>Fiche Membre Jean Rabe</html>');

        $controller = new MembreFicheController($twig, $attendanceStatsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Fiche Membre Jean Rabe', $content);
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Mahamasina');

        $groupe = new Groupe();
        $groupe->setNom('Zone Sud');

        $assoc = new Association();
        $assoc->setNom('STK');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(202);
        $member->method('getNom')->willReturn('Rasoa');
        $member->method('getPrenom')->willReturn('Marie');
        $member->method('getEmail')->willReturn('marie.rasoa@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getAdresse')->willReturn('Anosy Antananarivo');
        $member->method('getSexe')->willReturn('F');
        $member->method('getDateNaissance')->willReturn(new \DateTime('2000-01-20'));
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('QR_TOKEN_MARIE_202');

        $dummyStats = [
            'participationRate' => 90.0,
            'totalActivitiesCount' => 10,
            'attendedActivitiesCount' => 9,
        ];

        $attendanceStatsService = $this->createMock(AttendanceStatsService::class);
        $attendanceStatsService->expects($this->once())
            ->method('getMemberStats')
            ->with($member, 2025)
            ->willReturn($dummyStats);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $attendanceStatsService);

        $request = Request::create('/api/membres/202/fiche?format=json&year=2025');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(202, $data['id']);
        $this->assertEquals(202, $data['memberId']);
        $this->assertEquals('Rasoa', $data['nom']);
        $this->assertEquals('Marie', $data['prenom']);
        $this->assertEquals('marie.rasoa@example.com', $data['email']);
        $this->assertEquals('+261320011223', $data['telephone']);
        $this->assertEquals('Femme', $data['sexeLabel']);
        $this->assertEquals('Paroisse Mahamasina', $data['fiangonanaNom']);
        $this->assertEquals('Zone Sud', $data['groupeNom']);
        $this->assertEquals(['STK'], $data['associations']);
        $this->assertEquals('QR_TOKEN_MARIE_202', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertEquals(90.0, $data['stats']['participationRate']);
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
