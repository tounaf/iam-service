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
        $fiangonana->setNom('Paroisse Saint Jean');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261320011223');
        $member->method('getSexe')->willReturn('M');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1995-04-12'));
        $member->method('getAge')->willReturn(30);
        $member->method('getPhotoUrl')->willReturn(null);
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('SECRET_FICHE_TOKEN_999');

        $attendanceService = $this->createMock(AttendanceStatsService::class);
        $attendanceService->method('getMemberStats')->willReturn([
            'totalEventsCount' => 10,
            'attendedCount' => 8,
            'tauxPresence' => 80.0,
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
                        && $args['memberId'] === 101
                        && $args['token'] === 'SECRET_FICHE_TOKEN_999'
                        && str_contains($args['scanUrl'], '/membres/scan/SECRET_FICHE_TOKEN_999')
                        && $args['attendanceStats']['tauxPresence'] === 80.0;
                })
            )
            ->willReturn('<html>Fiche Jean Rabe - Paroisse Saint Jean - 80%</html>');

        $controller = new MembreFicheController($twig, $attendanceService);
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Jean Rabe', $content);
        $this->assertStringContainsString('Paroisse Saint Jean', $content);
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Centrale');

        $groupe = new Groupe();
        $groupe->setNom('Zone Analamanga');

        $assoc = new Association();
        $assoc->setNom('Section Jeunes');

        $role = new Role();
        $role->setName('PRESIDENT');

        $roleAssignment = new RoleAssignment();
        $roleAssignment->setRole($role);
        $roleAssignment->setAssociationContext($assoc);
        $roleAssignment->setExerciceYear('2026');
        $roleAssignment->setIsActive(true);

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(50);
        $member->method('getNom')->willReturn('Rasamoely');
        $member->method('getPrenom')->willReturn('Mami');
        $member->method('getEmail')->willReturn('mami@example.com');
        $member->method('getTelephone')->willReturn('+261340011223');
        $member->method('getSexe')->willReturn('F');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1998-01-15'));
        $member->method('getAge')->willReturn(28);
        $member->method('getPhotoUrl')->willReturn('https://example.com/mami.jpg');
        $member->method('getQrCodeToken')->willReturn('token-mami-50');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection([$roleAssignment]));

        $attendanceService = $this->createMock(AttendanceStatsService::class);
        $attendanceService->method('getMemberStats')->willReturn([
            'totalEventsCount' => 12,
            'attendedCount' => 12,
            'tauxPresence' => 100.0,
            'lateCount' => 0,
        ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $attendanceService);

        $request = Request::create('/api/membres/50/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(50, $data['id']);
        $this->assertEquals('Rasamoely', $data['nom']);
        $this->assertEquals('Mami', $data['prenom']);
        $this->assertEquals('mami@example.com', $data['email']);
        $this->assertEquals('F', $data['sexe']);
        $this->assertEquals('Paroisse Centrale', $data['fiangonanaNom']);
        $this->assertEquals('Zone Analamanga', $data['groupeNom']);
        $this->assertEquals(['Section Jeunes'], $data['associations']);
        $this->assertEquals('token-mami-50', $data['qrCodeToken']);
        $this->assertNotEmpty($data['qrCodeBase64']);
        $this->assertEquals(100.0, $data['attendanceStats']['tauxPresence']);
        $this->assertCount(1, $data['roles']);
        $this->assertEquals('PRESIDENT', $data['roles'][0]['role']);
    }

    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $twig = $this->createMock(Environment::class);
        $attendanceService = $this->createMock(AttendanceStatsService::class);

        $controller = new MembreFicheController($twig, $attendanceService);
        $controller->__invoke(null);
    }
}
