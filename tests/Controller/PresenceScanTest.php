<?php

namespace App\Tests\Controller;

use App\Controller\PresenceScanController;
use App\Entity\Membre;
use App\Entity\Presence;
use App\Repository\MembreRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Twig\Environment;

class PresenceScanTest extends TestCase
{
    public function testScanWithInvalidTokenReturns404Html(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('presence/error.html.twig', [
                'title' => 'Code QR non reconnu',
                'message' => 'Le code QR scanné ne correspond à aucun membre enregistré.'
            ])
            ->willReturn('<html>Error</html>');

        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(MembreRepository::class);
        $repo->method('findOneBy')->with(['qrCodeToken' => 'INVALID_TOKEN'])->willReturn(null);
        $em->method('getRepository')->with(Membre::class)->willReturn($repo);

        $controller = new PresenceScanController($twig);
        $request = Request::create('/membres/scan/INVALID_TOKEN');

        $response = $controller('INVALID_TOKEN', $request, $em);

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertEquals('<html>Error</html>', $response->getContent());
    }

    public function testScanWithInvalidTokenReturns404JsonWhenRequested(): void
    {
        $twig = $this->createMock(Environment::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(MembreRepository::class);
        $repo->method('findOneBy')->with(['qrCodeToken' => 'INVALID_TOKEN'])->willReturn(null);
        $em->method('getRepository')->with(Membre::class)->willReturn($repo);

        $controller = new PresenceScanController($twig);
        $request = Request::create('/membres/scan/INVALID_TOKEN?format=json');

        $response = $controller('INVALID_TOKEN', $request, $em);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Code QR non reconnu', $data['error']);
    }

    public function testScanGetValidTokenReturnsFormHtml(): void
    {
        $membre = new Membre();
        $membre->setNom('Andria');
        $membre->setPrenom('Lova');
        $membre->setQrCodeToken('VALID_TOKEN');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('presence/form.html.twig', [
                'nomComplet' => 'Lova Andria',
                'fiangonanaNom' => 'Paroisse',
                'error' => null
            ])
            ->willReturn('<html>Form</html>');

        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(MembreRepository::class);
        $repo->method('findOneBy')->with(['qrCodeToken' => 'VALID_TOKEN'])->willReturn($membre);
        $em->method('getRepository')->with(Membre::class)->willReturn($repo);

        $controller = new PresenceScanController($twig);
        $request = Request::create('/membres/scan/VALID_TOKEN');

        $response = $controller('VALID_TOKEN', $request, $em);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('<html>Form</html>', $response->getContent());
    }

    public function testScanPostRecordsPresenceSuccessfully(): void
    {
        $membre = new Membre();
        $membre->setNom('Rabe');
        $membre->setPrenom('Jean');
        $membre->setQrCodeToken('VALID_TOKEN');

        $responsable = new Membre();
        $responsable->setNom('Chef');
        $responsable->setPrenom('Formateur');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('presence/success.html.twig', $this->callback(function ($args) {
                return $args['nomComplet'] === 'Jean Rabe'
                    && $args['activityName'] === 'Formation Jeunes 2026';
            }))
            ->willReturn('<html>Success</html>');

        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(MembreRepository::class);
        $repo->method('findOneBy')->with(['qrCodeToken' => 'VALID_TOKEN'])->willReturn($membre);
        $em->method('getRepository')->with(Membre::class)->willReturn($repo);

        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Presence::class));
        $em->expects($this->once())->method('flush');

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($responsable);
        $tokenStorage->method('getToken')->willReturn($token);

        $controller = new PresenceScanController($twig);
        $request = Request::create('/membres/scan/VALID_TOKEN', 'POST', [
            'activityName' => 'Formation Jeunes 2026'
        ]);

        $response = $controller('VALID_TOKEN', $request, $em, $tokenStorage);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('<html>Success</html>', $response->getContent());
    }
}
