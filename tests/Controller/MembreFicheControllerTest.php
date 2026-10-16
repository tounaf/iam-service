<?php

namespace App\Tests\Controller;

use App\Controller\MembreFicheController;
use App\Entity\Membre;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Association;
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

        $role = new Role();
        $role->setName('Responsable Jeunesse');

        $roleAssignment = new RoleAssignment();
        $roleAssignment->setRole($role);
        $roleAssignment->setFiangonanaContext($fiangonana);
        $roleAssignment->setExerciceYear('2025');
        $roleAssignment->setIsActive(true);

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getSexe')->willReturn('M');
        $member->method('getAdresse')->willReturn('Lot IB 123 Antananarivo');
        $member->method('getAge')->willReturn(25);
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection([$roleAssignment]));
        $member->method('getQrCodeToken')->willReturn('MEMBER_TOKEN_101');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'year' => 2025,
            'totalActivitiesCount' => 10,
            'attendedActivitiesCount' => 9,
            'participationRate' => 90.0,
            'onTimeRate' => 100.0,
            'lateCount' => 0,
        ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Jean'
                        && $args['fiangonanaNom'] === 'Paroisse Ambohimanarina'
                        && $args['groupeNom'] === 'Zone Nord'
                        && $args['memberId'] === 101
                        && $args['token'] === 'MEMBER_TOKEN_101'
                        && $args['stats']['participationRate'] === 90.0;
                })
            )
            ->willReturn('<html>Fiche Jean Rabe</html>');

        $controller = new MembreFicheController($twig, $statsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Fiche Jean Rabe', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Central');

        $groupe = new Groupe();
        $groupe->setNom('Zone Analamanga');

        $assoc = new Association();
        $assoc->setNom('Sampana Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(50);
        $member->method('getNom')->willReturn('Rasolo');
        $member->method('getPrenom')->willReturn('Soa');
        $member->method('getEmail')->willReturn('soa@example.com');
        $member->method('getTelephone')->willReturn('+261340011223');
        $member->method('getSexe')->willReturn('F');
        $member->method('getAdresse')->willReturn('Lot V 45');
        $member->method('getAge')->willReturn(22);
        $member->method('getQrCodeToken')->willReturn('token-soa-50');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'year' => (int)date('Y'),
            'totalActivitiesCount' => 5,
            'attendedActivitiesCount' => 5,
            'participationRate' => 100.0,
        ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/50/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(50, $data['id']);
        $this->assertEquals('Rasolo', $data['nom']);
        $this->assertEquals('Soa', $data['prenom']);
        $this->assertEquals('soa@example.com', $data['email']);
        $this->assertEquals('+261340011223', $data['telephone']);
        $this->assertEquals('F', $data['sexe']);
        $this->assertEquals(22, $data['age']);
        $this->assertEquals('Paroisse Central', $data['fiangonanaNom']);
        $this->assertEquals('Zone Analamanga', $data['groupeNom']);
        $this->assertEquals(['Sampana Tanora'], $data['associations']);
        $this->assertEquals('token-soa-50', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertEquals(100.0, $data['stats']['participationRate']);
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
