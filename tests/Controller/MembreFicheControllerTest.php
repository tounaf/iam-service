<?php

namespace App\Tests\Controller;

use App\Entity\Association;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Membre;
use App\Controller\MembreFicheController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

class MembreFicheControllerTest extends WebTestCase
{
    public function testInvokeReturnsHtmlForValidMembre(): void
    {
        $membre = new Membre();
        $membre->setNom('Rakoto');
        $membre->setPrenom('Jean');
        $membre->setEmail('jean.rakoto@example.com');
        $membre->setTelephone('0340000000');
        $membre->setAdresse('Antananarivo');
        $membre->setSexe('M');
        $membre->setQrCodeToken('TEST-TOKEN-123');

        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Fiangonana Central');
        $membre->setFiangonana($fiangonana);

        $groupe = new Groupe();
        $groupe->setNom('Zone Nord');
        $membre->setZoneGeographique($groupe);

        $association = new Association();
        $association->setNom('Sampana Tanora');
        $membre->addAssociation($association);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'membre/fiche.html.twig',
                $this->callback(function (array $context) {
                    return $context['nom'] === 'Rakoto'
                        && $context['prenom'] === 'Jean'
                        && $context['email'] === 'jean.rakoto@example.com'
                        && $context['fiangonanaNom'] === 'Fiangonana Central'
                        && $context['groupeNom'] === 'Zone Nord'
                        && $context['token'] === 'TEST-TOKEN-123';
                })
            )
            ->willReturn('<html>Fiche Content</html>');

        $controller = new MembreFicheController($twig);
        $request = Request::create('/api/membres/1/fiche');

        $response = $controller($membre, $request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertEquals('<html>Fiche Content</html>', $response->getContent());
    }

    public function testInvokeReturnsJsonWhenRequested(): void
    {
        $membre = new Membre();
        $membre->setNom('Rasoa');
        $membre->setPrenom('Marie');
        $membre->setEmail('marie.rasoa@example.com');
        $membre->setTelephone('0320000000');
        $membre->setAdresse('Fianarantsoa');
        $membre->setSexe('F');
        $membre->setQrCodeToken('TOKEN-MARIE-456');

        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig);

        $request = Request::create('/api/membres/2/fiche?format=json');
        $response = $controller($membre, $request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Rasoa', $data['nom']);
        $this->assertEquals('Marie', $data['prenom']);
        $this->assertEquals('marie.rasoa@example.com', $data['email']);
        $this->assertEquals('TOKEN-MARIE-456', $data['qrCodeToken']);
        $this->assertArrayHasKey('qrCodeBase64', $data);
    }

    public function testInvokeThrowsNotFoundWhenMembreIsNull(): void
    {
        $twig = $this->createMock(Environment::class);
        $controller = new MembreFicheController($twig);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $controller(null, Request::create('/api/membres/999/fiche'));
    }
}
