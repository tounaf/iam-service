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
        $fiangonana->setNom('Paroisse Ambohimanarina');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $assoc = new Association();
        $assoc->setNom('KTLM');

        $role = new Role();
        $role->setName('Président');

        $roleAssignment = new RoleAssignment();
        $roleAssignment->setRole($role);

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(100);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261341234567');
        $member->method('getSexe')->willReturn('M');
        $member->method('getAdresse')->willReturn('Lot II A 12 Antananarivo');
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/jean.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection([$roleAssignment]));
        $member->method('getQrCodeToken')->willReturn('SECRET_QR_TOKEN_999');

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
                        && $args['memberId'] === 100
                        && $args['token'] === 'SECRET_QR_TOKEN_999'
                        && !empty($args['rolesList'])
                        && str_contains(strtoupper($args['rolesList'][0]), 'PR')
                        && isset($args['stats']['participationRate']);
                })
            )
            ->willReturn('<html>Fiche Membre Jean Rabe</html>');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'year' => 2026,
            'totalActivitiesCount' => 10,
            'attendedActivitiesCount' => 8,
            'participationRate' => 80.0,
            'lateCount' => 1,
            'entityStats' => [
                'associations' => [],
                'groupe' => null,
                'fiangonana' => null
            ]
        ]);

        $controller = new MembreFicheController($twig, $statsService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Fiche Membre Jean Rabe', $response->getContent());
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Centrale');

        $groupe = new Groupe();
        $groupe->setNom('Zone Est');

        $assoc = new Association();
        $assoc->setNom('Choeur d\'hommes');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(200);
        $member->method('getNom')->willReturn('Rasalama');
        $member->method('getPrenom')->willReturn('Marie');
        $member->method('getEmail')->willReturn('marie@example.com');
        $member->method('getTelephone')->willReturn('+261320102030');
        $member->method('getSexe')->willReturn('F');
        $member->method('getAdresse')->willReturn('Rue Principale');
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getQrCodeToken')->willReturn('token-marie-777');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());

        $twig = $this->createMock(Environment::class);
        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'year' => 2026,
            'totalActivitiesCount' => 5,
            'attendedActivitiesCount' => 5,
            'participationRate' => 100.0,
            'lateCount' => 0,
            'entityStats' => []
        ]);

        $controller = new MembreFicheController($twig, $statsService);
        $request = Request::create('/api/membres/200/fiche?format=json');

        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(200, $data['id']);
        $this->assertEquals('Rasalama', $data['nom']);
        $this->assertEquals('Marie', $data['prenom']);
        $this->assertEquals('F', $data['sexe']);
        $this->assertEquals('marie@example.com', $data['email']);
        $this->assertEquals('Paroisse Centrale', $data['fiangonanaNom']);
        $this->assertEquals('Zone Est', $data['groupeNom']);
        $this->assertEquals(['Choeur d\'hommes'], $data['associations']);
        $this->assertEquals('token-marie-777', $data['qrCodeToken']);
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
