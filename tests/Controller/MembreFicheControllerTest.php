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
        $fiangonana->setNom('Paroisse Ambohimanarina');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $assoc = new Association();
        $assoc->setNom('Sampana Mpanazava');

        $role = new Role();
        $role->setName('PRESIDENT');

        $assignment = new RoleAssignment();
        $assignment->setRole($role);
        $assignment->setAssociationContext($assoc);
        $assignment->setIsActive(true);
        $assignment->setExerciceYear('2026');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(7);
        $member->method('getNom')->willReturn('Randria');
        $member->method('getPrenom')->willReturn('Tolotra');
        $member->method('getEmail')->willReturn('tolotra@example.com');
        $member->method('getTelephone')->willReturn('+261341122334');
        $member->method('getAdresse')->willReturn('Lot II Y 45 Ambohimanarina');
        $member->method('getSexe')->willReturn('M');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1998-05-12'));
        $member->method('getAge')->willReturn(28);
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/tolotra.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection([$assignment]));
        $member->method('getQrCodeToken')->willReturn('QR_TOKEN_TOLOTRA_777');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->expects($this->once())
            ->method('getMemberStats')
            ->with($member, 2026)
            ->willReturn([
                'year' => 2026,
                'totalActivitiesCount' => 20,
                'attendedActivitiesCount' => 18,
                'participationRate' => 90.0,
                'lateCount' => 2,
                'onTimeCount' => 16,
                'lateRate' => 11.1,
                'onTimeRate' => 88.9,
                'entityStats' => [
                    'associations' => [
                        [
                            'nom' => 'Sampana Mpanazava',
                            'presenceRate' => 100.0,
                            'attended' => 10,
                            'totalEvents' => 10,
                        ]
                    ],
                    'groupe' => [
                        'nom' => 'Zone Nord',
                        'presenceRate' => 80.0,
                        'attended' => 8,
                        'totalEvents' => 10,
                    ],
                    'fiangonana' => [
                        'nom' => 'Paroisse Ambohimanarina',
                        'presenceRate' => 90.0,
                        'attended' => 18,
                        'totalEvents' => 20,
                    ]
                ]
            ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['memberId'] === 7
                        && $args['nom'] === 'Randria'
                        && $args['prenom'] === 'Tolotra'
                        && $args['email'] === 'tolotra@example.com'
                        && $args['telephone'] === '+261341122334'
                        && $args['adresse'] === 'Lot II Y 45 Ambohimanarina'
                        && $args['sexe'] === 'M'
                        && $args['age'] === 28
                        && $args['fiangonanaNom'] === 'Paroisse Ambohimanarina'
                        && $args['groupeNom'] === 'Zone Nord'
                        && $args['token'] === 'QR_TOKEN_TOLOTRA_777'
                        && !empty($args['qrCodeBase64'])
                        && count($args['activeRoles']) === 1
                        && $args['activeRoles'][0]['roleName'] === 'PRESIDENT'
                        && $args['stats']['participationRate'] === 90.0;
                })
            )
            ->willReturn('<html>Fiche Tolotra Randria</html>');

        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/7/fiche?year=2026');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertEquals('<html>Fiche Tolotra Randria</html>', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Centrale');

        $groupe = new Groupe();
        $groupe->setNom('Zone Ouest');

        $assoc = new Association();
        $assoc->setNom('Chorale Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(14);
        $member->method('getNom')->willReturn('Rasoanaivo');
        $member->method('getPrenom')->willReturn('Mialy');
        $member->method('getEmail')->willReturn('mialy@example.com');
        $member->method('getTelephone')->willReturn('+261330011223');
        $member->method('getAdresse')->willReturn('Analakely');
        $member->method('getSexe')->willReturn('F');
        $member->method('getDateNaissance')->willReturn(new \DateTime('2001-09-15'));
        $member->method('getAge')->willReturn(25);
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('TOKEN_MIALY_123');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')
            ->willReturn([
                'year' => 2026,
                'participationRate' => 85.5,
                'totalActivitiesCount' => 10,
                'attendedActivitiesCount' => 8,
            ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/14/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(14, $data['id']);
        $this->assertEquals(14, $data['memberId']);
        $this->assertEquals('Rasoanaivo', $data['nom']);
        $this->assertEquals('Mialy', $data['prenom']);
        $this->assertEquals('mialy@example.com', $data['email']);
        $this->assertEquals('+261330011223', $data['telephone']);
        $this->assertEquals('Analakely', $data['adresse']);
        $this->assertEquals('F', $data['sexe']);
        $this->assertEquals(25, $data['age']);
        $this->assertEquals('Paroisse Centrale', $data['fiangonanaNom']);
        $this->assertEquals('Zone Ouest', $data['groupeNom']);
        $this->assertEquals(['Chorale Tanora'], $data['associations']);
        $this->assertEquals('TOKEN_MIALY_123', $data['qrCodeToken']);
        $this->assertStringContainsString('/membres/scan/TOKEN_MIALY_123', $data['scanUrl']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertEquals(85.5, $data['stats']['participationRate']);
    }

    public function testInvokeReturnsRawTokenInQrCodeWhenRawParamSet(): void
    {
        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(20);
        $member->method('getNom')->willReturn('Rakoto');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('RAW_SECRET_CODE');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn(['participationRate' => 0]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/20/fiche?format=json&raw=1');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('RAW_SECRET_CODE', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
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
