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
        $fiangonana->setNom('Fiangonana Ampandrana');

        $groupe = new Groupe();
        $groupe->setNom('Zone Anosy');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(100);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261320001122');
        $member->method('getAdresse')->willReturn('Lot III B Antananarivo');
        $member->method('getSexe')->willReturn('M');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1995-05-12'));
        $member->method('getAge')->willReturn(29);
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/jean.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('TOKEN_JEAN_100');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'totalActivitiesCount' => 10,
            'attendedActivitiesCount' => 8,
            'participationRate' => 80.0,
            'lateCount' => 1,
            'lateRate' => 12.5,
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
                        && $args['fiangonanaNom'] === 'Fiangonana Ampandrana'
                        && $args['groupeNom'] === 'Zone Anosy'
                        && $args['memberId'] === 100
                        && $args['token'] === 'TOKEN_JEAN_100'
                        && str_contains($args['scanUrl'], '/membres/scan/TOKEN_JEAN_100')
                        && $args['stats']['participationRate'] === 80.0;
                })
            )
            ->willReturn('<html>Fiche Membre Jean Rabe - Fiangonana Ampandrana</html>');

        $controller = new MembreFicheController($twig, $statsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Jean Rabe', $content);
        $this->assertStringContainsString('Fiangonana Ampandrana', $content);
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Central');

        $groupe = new Groupe();
        $groupe->setNom('Zone Est');

        $assoc = new Association();
        $assoc->setNom('Chorale KT');

        $role = new Role();
        $role->setName('PRESIDENT');

        $roleAssignment = new RoleAssignment();
        $roleAssignment->setRole($role);
        $roleAssignment->setAssociationContext($assoc);
        $roleAssignment->setExerciceYear('2026');
        $roleAssignment->setIsActive(true);

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(200);
        $member->method('getNom')->willReturn('Rasoa');
        $member->method('getPrenom')->willReturn('Bako');
        $member->method('getEmail')->willReturn('bako.rasoa@example.com');
        $member->method('getTelephone')->willReturn('+261340011223');
        $member->method('getAdresse')->willReturn('Lot IV A Mahamasina');
        $member->method('getSexe')->willReturn('F');
        $member->method('getQrCodeToken')->willReturn('TOKEN_RASOA_200');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection([$roleAssignment]));

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'totalActivitiesCount' => 15,
            'attendedActivitiesCount' => 15,
            'participationRate' => 100.0,
            'lateCount' => 0,
            'lateRate' => 0.0,
            'presenceLogs' => [
                [
                    'activityName' => 'Formation des Jeunes 2026',
                    'scannedAt' => '2026-03-15T10:00:00+00:00',
                    'isLate' => false,
                    'delayMinutes' => 0,
                    'scannedBy' => null
                ]
            ]
        ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/200/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(200, $data['id']);
        $this->assertEquals(200, $data['memberId']);
        $this->assertEquals('Rasoa', $data['nom']);
        $this->assertEquals('Bako', $data['prenom']);
        $this->assertEquals('bako.rasoa@example.com', $data['email']);
        $this->assertEquals('Paroisse Central', $data['fiangonanaNom']);
        $this->assertEquals('Zone Est', $data['groupeNom']);
        $this->assertEquals(['Chorale KT'], $data['associations']);
        $this->assertEquals('TOKEN_RASOA_200', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertCount(1, $data['activeRoles']);
        $this->assertEquals('PRESIDENT', $data['activeRoles'][0]['role']);
        $this->assertEquals(100.0, $data['stats']['participationRate']);
        $this->assertCount(1, $data['stats']['presenceLogs']);
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
