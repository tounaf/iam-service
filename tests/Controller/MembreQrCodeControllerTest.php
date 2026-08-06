<?php

namespace App\Tests\Controller;

use App\Controller\MembreQrCodeController;
use App\Entity\Membre;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MembreQrCodeControllerTest extends TestCase
{
    public function testInvokeReturnsPngResponseForValidMember(): void
    {
        $member = new Membre();
        $member->setQrCodeToken('test_secure_token_123');

        $controller = new MembreQrCodeController();
        $response = $controller->__invoke($member);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('image/png', $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->getContent());
    }

    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $controller = new MembreQrCodeController();
        $controller->__invoke(null);
    }
}
