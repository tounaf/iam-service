<?php

namespace App\Tests\Controller;

use App\Controller\MembreFicheController;
use App\Entity\Membre;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Association;
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
        $fiangonana->setNom('Paroisse Test');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $assoc = new Association();
        $assoc->setNom('Jeunesse Tanora');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(100);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261340011223');
        $member->method('getSexe')->willReturn('M');
        $member->method('getAdresse')->willReturn('Antananarivo');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1998-05-15'));
        $member->method('getAge')->willReturn(26);
        $member->method('getPhotoUrl')->willReturn('/uploads/membres/rabe.jpg');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('FICHE_TOKEN_999');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->expects($this->once())
            ->method('getMemberStats')
            ->willReturn([
                'totalActivitiesCount' => 10,
                'attendedActivitiesCount' => 8,
                'participationRate' => 80.0,
                'lateCount' => 1,
                'onTimeCount' => 7
            ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Jean'
                        && $args['fiangonanaNom'] === 'Paroisse Test'
                        && $args['groupeNom'] === 'Zone Nord'
                        && $args['memberId'] === 100
                        && $args['token'] === 'FICHE_TOKEN_999'
                        && $args['stats']['participationRate'] === 80.0;
                })
            )
            ->willReturn('<html>Fiche Membre Jean Rabe</html>');

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
        $fiangonana->setNom('Paroisse Sud');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Rasao');
        $member->method('getPrenom')->willReturn('Mary');
        $member->method('getEmail')->willReturn('mary.rasao@example.com');
        $member->method('getTelephone')->willReturn('+261320099887');
        $member->method('getSexe')->willReturn('F');
        $member->method('getAdresse')->willReturn('Fianarantsoa');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getRoleAssignments')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('FICHE_TOKEN_JSON');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'totalActivitiesCount' => 5,
            'attendedActivitiesCount' => 5,
            'participationRate' => 100.0,
            'lateCount' => 0,
            'onTimeCount' => 5
        ]);

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig, $statsService);

        $request = Request::create('/api/membres/101/fiche?format=json');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(101, $data['id']);
        $this->assertEquals('Rasao', $data['nom']);
        $this->assertEquals('Mary', $data['prenom']);
        $this->assertEquals('Paroisse Sud', $data['fiangonanaNom']);
        $this->assertEquals('FICHE_TOKEN_JSON', $data['qrCodeToken']);
        $this->assertEquals(100.0, $data['participationStats']['participationRate']);
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
