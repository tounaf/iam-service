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
        $fiangonana->setNom('Paroisse Internationale');

        $groupe = new Groupe();
        $groupe->setNom('Zone Centre');

        $assoc = new Association();
        $assoc->setNom('Chorale des Jeunes');

        $role = new Role();
        $role->setName('PRESIDENT');

        $roleAssignment = new RoleAssignment();
        $roleAssignment->setRole($role);
        $roleAssignment->setAssociationContext($assoc);
        $roleAssignment->setIsActive(true);
        $roleAssignment->setExerciceYear('2026');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(50);
        $member->method('getNom')->willReturn('Randria');
        $member->method('getPrenom')->willReturn('Mamy');
        $member->method('getEmail')->willReturn('mamy@example.com');
        $member->method('getTelephone')->willReturn('+261320000001');
        $member->method('getAdresse')->willReturn('Lot 123 Rue de la Paix');
        $member->method('getSexe')->willReturn('M');
        $member->method('getAge')->willReturn(28);
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/mamy.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection([$roleAssignment]));
        $member->method('getQrCodeToken')->willReturn('MAMY_TOKEN_999');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'participationRate' => 85.5,
            'totalActivitiesCount' => 20,
            'attendedActivitiesCount' => 17,
            'onTimeCount' => 15,
            'lateCount' => 2,
        ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Randria'
                        && $args['prenom'] === 'Mamy'
                        && $args['fiangonanaNom'] === 'Paroisse Internationale'
                        && $args['groupeNom'] === 'Zone Centre'
                        && $args['memberId'] === 50
                        && $args['token'] === 'MAMY_TOKEN_999'
                        && str_contains($args['scanUrl'], '/membres/scan/MAMY_TOKEN_999')
                        && isset($args['stats']['participationRate'])
                        && $args['stats']['participationRate'] === 85.5;
                })
            )
            ->willReturn('<html>Fiche Membre - Mamy Randria</html>');

        $controller = new MembreFicheController($twig, $statsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Fiche Membre - Mamy Randria', $content);
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Sud');

        $groupe = new Groupe();
        $groupe->setNom('Zone Est');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(51);
        $member->method('getNom')->willReturn('Andria');
        $member->method('getPrenom')->willReturn('Soa');
        $member->method('getEmail')->willReturn('soa@example.com');
        $member->method('getTelephone')->willReturn('+261330000002');
        $member->method('getAdresse')->willReturn('Ankadifotsy');
        $member->method('getSexe')->willReturn('F');
        $member->method('getAge')->willReturn(24);
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('SOA_TOKEN_777');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'participationRate' => 100.0,
            'totalActivitiesCount' => 10,
            'attendedActivitiesCount' => 10,
            'onTimeCount' => 10,
            'lateCount' => 0,
        ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/51/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(51, $data['id']);
        $this->assertEquals('Andria', $data['nom']);
        $this->assertEquals('Soa', $data['prenom']);
        $this->assertEquals('soa@example.com', $data['email']);
        $this->assertEquals('Paroisse Sud', $data['fiangonanaNom']);
        $this->assertEquals('Zone Est', $data['groupeNom']);
        $this->assertEquals('SOA_TOKEN_777', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertStringContainsString('/membres/scan/SOA_TOKEN_777', $data['scanUrl']);
        $this->assertEquals(100.0, $data['stats']['participationRate']);
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
