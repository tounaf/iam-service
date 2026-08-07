<?php

namespace App\Tests\Controller;

use App\Controller\MembreCarteController;
use App\Entity\Membre;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Association;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MembreCarteControllerTest extends TestCase
{
    public function testInvokeReturnsBeautifulHtmlCard(): void
    {
        $church = new Fiangonana();
        $church->setNom('Paroisse de Test');

        $group = new Groupe();
        $group->setNom('Zone Ouest');

        $association = new Association();
        $association->setNom('Chorale Tanora');

        $member = new Membre();
        $member->setNom('Rabe');
        $member->setPrenom('Jean');
        $member->setEmail('jean.rabe@example.com');
        $member->setTelephone('+261341111111');
        $member->setFiangonana($church);
        $member->setZoneGeographique($group);
        $member->addAssociation($association);
        $member->setQrCodeToken('unique_token_xyz_123');

        $controller = new MembreCarteController();
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/html; charset=UTF-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('Rabe', $content);
        $this->assertStringContainsString('Jean', $content);
        $this->assertStringContainsString('Paroisse de Test', $content);
        $this->assertStringContainsString('Zone Ouest', $content);
        $this->assertStringContainsString('Chorale Tanora', $content);
        $this->assertStringContainsString('data:image/png;base64,', $content);
    }

    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $controller = new MembreCarteController();
        $controller->__invoke(null);
    }
}
