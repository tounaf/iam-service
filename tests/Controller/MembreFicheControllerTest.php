<?php

namespace App\Tests\Controller;

use App\Controller\MembreFicheController;
use App\Entity\Membre;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Association;
use App\Entity\RoleAssignment;
use App\Entity\Role;
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
        $fiangonana->setNom('Test Parish');

        $groupe = new Groupe();
        $groupe->setNom('Test Zone');

        $assoc = new Association();
        $assoc->setNom('Jeunesse KT');

        $role = new Role();
        $role->setName('RESPONSABLE');

        $roleAssignment = new RoleAssignment();
        $roleAssignment->setRole($role);
        $roleAssignment->setExerciceYear(2026);
        $roleAssignment->setAssociationContext($assoc);

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Andria');
        $member->method('getPrenom')->willReturn('Soa');
        $member->method('getEmail')->willReturn('soa@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getSexe')->willReturn('F');
        $member->method('getAdresse')->willReturn('Antananarivo');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1998-05-15'));
        $member->method('getAge')->willReturn(27);
        $member->method('getPhotoUrl')->willReturn('https://example.com/soa.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection([$roleAssignment]));
        $member->method('getQrCodeToken')->willReturn('SECRET_TOKEN_FICHE_123');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'participationRate' => 85.5,
            'totalActivitiesCount' => 10,
            'attendedActivitiesCount' => 8,
            'onTimeCount' => 7,
            'lateCount' => 1,
        ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Andria'
                        && $args['prenom'] === 'Soa'
                        && $args['fiangonanaNom'] === 'Test Parish'
                        && $args['groupeNom'] === 'Test Zone'
                        && $args['memberId'] === 101
                        && $args['token'] === 'SECRET_TOKEN_FICHE_123'
                        && $args['stats']['participationRate'] === 85.5;
                })
            )
            ->willReturn('<html>Fiche Soa Andria - Test Parish</html>');

        $controller = new MembreFicheController($twig, $statsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Fiche Soa Andria', $content);
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse St Michel');

        $groupe = new Groupe();
        $groupe->setNom('Zone Est');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(202);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean@example.com');
        $member->method('getTelephone')->willReturn('+261330011223');
        $member->method('getSexe')->willReturn('M');
        $member->method('getAdresse')->willReturn('Tamatave');
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getDateNaissance')->willReturn(null);
        $member->method('getAge')->willReturn(null);
        $member->method('getQrCodeToken')->willReturn('token-jean-202');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'participationRate' => 100.0,
            'totalActivitiesCount' => 5,
            'attendedActivitiesCount' => 5,
        ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/202/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(202, $data['id']);
        $this->assertEquals(202, $data['memberId']);
        $this->assertEquals('Rabe', $data['nom']);
        $this->assertEquals('Jean', $data['prenom']);
        $this->assertEquals('Paroisse St Michel', $data['fiangonanaNom']);
        $this->assertEquals('Zone Est', $data['groupeNom']);
        $this->assertEquals('token-jean-202', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertArrayHasKey('stats', $data);
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
