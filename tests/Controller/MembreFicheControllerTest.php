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
        $fiangonana->setNom('Paroisse Saint-Paul');

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(101);
        $member->method('getNom')->willReturn('Rabe');
        $member->method('getPrenom')->willReturn('Jean');
        $member->method('getEmail')->willReturn('jean.rabe@example.com');
        $member->method('getTelephone')->willReturn('+261340011223');
        $member->method('getAdresse')->willReturn('Lot II M 40 Ankorondrano');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1995-05-15'));
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection());
        $member->method('getQrCodeToken')->willReturn('TOKEN_FICHE_101');

        $statsService = $this->createMock(AttendanceStatsService::class);
        $mockStats = [
            'year' => 2026,
            'totalActivitiesCount' => 12,
            'attendedActivitiesCount' => 10,
            'participationRate' => 83.33,
            'lateCount' => 2,
            'onTimeCount' => 8,
            'lateRate' => 20.0,
            'onTimeRate' => 80.0,
        ];
        $statsService->expects($this->once())
            ->method('getMemberStats')
            ->with($member, 2026)
            ->willReturn($mockStats);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function ($args) {
                    return $args['nom'] === 'Rabe'
                        && $args['prenom'] === 'Jean'
                        && $args['fiangonanaNom'] === 'Paroisse Saint-Paul'
                        && $args['groupeNom'] === 'Zone Nord'
                        && $args['memberId'] === 101
                        && $args['token'] === 'TOKEN_FICHE_101'
                        && $args['stats']['participationRate'] === 83.33;
                })
            )
            ->willReturn('<html>Fiche Membre Jean Rabe - Paroisse Saint-Paul</html>');

        $controller = new MembreFicheController($twig, $statsService);
        $request = Request::create('/api/membres/101/fiche?year=2026');
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Jean Rabe', $content);
        $this->assertStringContainsString('Paroisse Saint-Paul', $content);
    }

    public function testInvokeReturnsJsonResponseWhenJsonRequested(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Paroisse Ambohimanarina');

        $groupe = new Groupe();
        $groupe->setNom('Zone Est');

        $assoc = new Association();
        $assoc->setNom('Chorale Fiderana');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(202);
        $member->method('getNom')->willReturn('Rasao');
        $member->method('getPrenom')->willReturn('Soa');
        $member->method('getEmail')->willReturn('soa@example.com');
        $member->method('getTelephone')->willReturn('+261320099887');
        $member->method('getAdresse')->willReturn('Lot IV G 12');
        $member->method('getDateNaissance')->willReturn(new \DateTime('1988-10-20'));
        $member->method('getQrCodeToken')->willReturn('token-soa-202');
        $member->method('getFiangonana')->willReturn($fiangonana);
        $member->method('getZoneGeographique')->willReturn($groupe);
        $member->method('getAssociations')->willReturn(new ArrayCollection([$assoc]));

        $statsService = $this->createMock(AttendanceStatsService::class);
        $statsService->method('getMemberStats')->willReturn([
            'year' => (int)date('Y'),
            'totalActivitiesCount' => 5,
            'attendedActivitiesCount' => 5,
            'participationRate' => 100.0,
            'lateCount' => 0,
            'onTimeCount' => 5,
            'lateRate' => 0.0,
            'onTimeRate' => 100.0,
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
        $this->assertEquals('Rasao', $data['nom']);
        $this->assertEquals('Soa', $data['prenom']);
        $this->assertEquals('soa@example.com', $data['email']);
        $this->assertEquals('+261320099887', $data['telephone']);
        $this->assertEquals('Lot IV G 12', $data['adresse']);
        $this->assertEquals('20/10/1988', $data['dateNaissance']);
        $this->assertEquals('Paroisse Ambohimanarina', $data['fiangonanaNom']);
        $this->assertEquals('Zone Est', $data['groupeNom']);
        $this->assertEquals(['Chorale Fiderana'], $data['associations']);
        $this->assertEquals('token-soa-202', $data['qrCodeToken']);
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
