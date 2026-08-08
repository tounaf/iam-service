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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

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

        $request = new Request();
        $controller = new PresenceScanController();

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

        $request = Request::create('/membres/scan/valid_token', 'GET');
        $controller = new PresenceScanController();

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

        $request = Request::create('/membres/scan/valid_token', 'POST', ['activityName' => '']);
        $controller = new PresenceScanController();

        $response = $controller->__invoke('valid_token', $request, $entityManager);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Veuillez saisir ou choisir le nom de l&#039;activité.', $response->getContent());
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

        $request = Request::create('/membres/scan/valid_token', 'POST', ['activityName' => 'Formation des Jeunes 2026']);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $controller = new PresenceScanController();

        $response = $controller->__invoke('valid_token', $request, $entityManager, $tokenStorage);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Présence Validée !', $response->getContent());
        $this->assertStringContainsString('Formation des Jeunes 2026', $response->getContent());
    }
}
