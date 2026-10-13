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
        $fiangonana->setNom('Église Ambohitrimanjaka');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $assoc = new Association();
        $assoc->setNom('Chorale Farimbona');

        $role = new Role();
        $role->setName('Responsable Jeunesse');

        $roleAssignment = new RoleAssignment();
        $roleAssignment->setRole($role);
        $roleAssignment->setExerciceYear(2026);

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Soa');
        $member->method('getSexe')->willReturn('F');
        $member->method('getEmail')->willReturn('soa@example.com');
        $member->method('getTelephone')->willReturn('+261320102030');
        $member->method('getAdresse')->willReturn('Lot IB 12 Antananarivo');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1998-05-15'));
        $member->method('getAge')->willReturn(27);
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection([$roleAssignment]));
        $member->method('getQrCodeToken')->willReturn('QR_SOA_101');

        $stats = [
            'totalEvents' => 20,
            'attendedCount' => 18,
            'presenceRate' => 90.0,
            'onTimeCount' => 15,
            'lateCount' => 3,
            'absentCount' => 2,
        ];

        $attendanceService = $this->createMock(AttendanceStatsService::class);
        $attendanceService->expects($this->once())
            ->method('getMemberStats')
            ->with($member, (int)date('Y'))
            ->willReturn($stats);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Soa'
                        && $args['fiangonanaNom'] === 'Église Ambohitrimanjaka'
                        && $args['groupeNom'] === 'Zone Nord'
                        && $args['associationsStr'] === 'Chorale Farimbona'
                        && $args['memberId'] === 101
                        && $args['token'] === 'QR_SOA_101'
                        && $args['stats']['presenceRate'] === 90.0;
                })
            )
            ->willReturn('<html>Fiche Soa Rabe</html>');

        $controller = new MembreFicheController($twig, $attendanceService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Fiche Soa Rabe', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Fiangonana Centrale');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(202);
        $member->method('getNom')->willReturn('Andria');
        $member->method('getPrenom')->willReturn('Koto');
        $member->method('getSexe')->willReturn('M');
        $member->method('getEmail')->willReturn('koto.andria@example.com');
        $member->method('getTelephone')->willReturn('+261331122334');
        $member->method('getAdresse')->willReturn('Analakely');
        $member->method('getDateNaissance')->willReturn(new \DateTime('2001-10-20'));
        $member->method('getAge')->willReturn(24);
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/koto.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn(null);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('TOKEN_KOTO_202');

        $stats = [
            'totalEvents' => 10,
            'attendedCount' => 10,
            'presenceRate' => 100.0,
            'onTimeCount' => 10,
            'lateCount' => 0,
            'absentCount' => 0,
        ];

        $attendanceService = $this->createMock(AttendanceStatsService::class);
        $attendanceService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn($stats);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $attendanceService);

        $request = Request::create('/api/membres/202/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(202, $data['id']);
        $this->assertEquals('Andria', $data['nom']);
        $this->assertEquals('Koto', $data['prenom']);
        $this->assertEquals('Fiangonana Centrale', $data['fiangonanaNom']);
        $this->assertEquals('Non spécifié', $data['groupeNom']);
        $this->assertEquals('TOKEN_KOTO_202', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertEquals(100.0, $data['stats']['presenceRate']);
    }

    public function testInvokeThrowsNotFoundExceptionWhenMemberIsNull(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $twig = $this->createMock(Environment::class);
        $attendanceService = $this->createMock(AttendanceStatsService::class);

        $controller = new MembreFicheController($twig, $attendanceService);
        $controller->__invoke(null);
    }
}
