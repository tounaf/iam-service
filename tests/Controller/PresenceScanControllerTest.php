<?php

namespace App\Tests\Controller;

use App\Controller\PresenceScanController;
use App\Entity\Membre;
use App\Entity\Presence;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class PresenceScanControllerTest extends TestCase
{
    public function testInvokeReturnsNotFoundForInvalidToken(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);

        $repository->method('findOneBy')
            ->with(['qrCodeToken' => 'invalid_token'])
            ->willReturn(null);

        $entityManager->method('getRepository')
            ->with(Membre::class)
            ->willReturn($repository);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('presence/error.html.twig', [
                'title' => 'Code QR non reconnu',
                'message' => 'Le code QR scanné ne correspond à aucun membre enregistré.'
            ])
            ->willReturn('Code QR non reconnu - Le code QR scanné ne correspond à aucun membre enregistré.');

        $request = new Request();
        $controller = new PresenceScanController($twig);

        $response = $controller->__invoke('invalid_token', $request, $entityManager);

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('Code QR non reconnu', $response->getContent());
    }

    public function testInvokeReturnsGetForm(): void
    {
        $member = new Membre();
        $member->setNom('Ratsimbazafy');
        $member->setPrenom('Nirina');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);

        $repository->method('findOneBy')
            ->with(['qrCodeToken' => 'valid_token'])
            ->willReturn($member);

        $entityManager->method('getRepository')
            ->with(Membre::class)
            ->willReturn($repository);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('presence/form.html.twig', [
                'nomComplet' => 'Nirina Ratsimbazafy',
                'fiangonanaNom' => 'Paroisse',
                'error' => null
            ])
            ->willReturn('Validation de Présence - Nirina Ratsimbazafy');

        $request = Request::create('/membres/scan/valid_token', 'GET');
        $controller = new PresenceScanController($twig);

        $response = $controller->__invoke('valid_token', $request, $entityManager);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Validation de Présence', $response->getContent());
        $this->assertStringContainsString('Nirina Ratsimbazafy', $response->getContent());
    }

    public function testInvokeReturnsBadRequestForEmptyActivityOnPost(): void
    {
        $member = new Membre();
        $member->setNom('Ratsimbazafy');
        $member->setPrenom('Nirina');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);

        $repository->method('findOneBy')
            ->with(['qrCodeToken' => 'valid_token'])
            ->willReturn($member);

        $entityManager->method('getRepository')
            ->with(Membre::class)
            ->willReturn($repository);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('presence/form.html.twig', [
                'nomComplet' => 'Nirina Ratsimbazafy',
                'fiangonanaNom' => 'Paroisse',
                'error' => 'Veuillez saisir ou choisir le nom de l\'activité.'
            ])
            ->willReturn('Veuillez saisir ou choisir le nom de l\'activité.');

        $request = Request::create('/membres/scan/valid_token', 'POST', ['activityName' => '']);
        $controller = new PresenceScanController($twig);

        $response = $controller->__invoke('valid_token', $request, $entityManager);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Veuillez saisir ou choisir le nom de l&#039;activité.', htmlspecialchars($response->getContent()));
    }

    public function testInvokeSavesPresenceAndReturnsSuccessOnValidPost(): void
    {
        $member = new Membre();
        $member->setNom('Ratsimbazafy');
        $member->setPrenom('Nirina');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);

        $repository->method('findOneBy')
            ->with(['qrCodeToken' => 'valid_token'])
            ->willReturn($member);

        $entityManager->method('getRepository')
            ->with(Membre::class)
            ->willReturn($repository);

        // Expect presence to be saved
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Presence::class));
        $entityManager->expects($this->once())
            ->method('flush');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('presence/success.html.twig', $this->callback(function ($args) {
                return $args['nomComplet'] === 'Nirina Ratsimbazafy'
                    && $args['activityName'] === 'Formation des Jeunes 2026'
                    && isset($args['date']);
            }))
            ->willReturn('Présence Validée ! - Formation des Jeunes 2026');

        $request = Request::create('/membres/scan/valid_token', 'POST', ['activityName' => 'Formation des Jeunes 2026']);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $controller = new PresenceScanController($twig);

        $response = $controller->__invoke('valid_token', $request, $entityManager, $tokenStorage);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Présence Validée !', $response->getContent());
        $this->assertStringContainsString('Formation des Jeunes 2026', $response->getContent());
    }
}
