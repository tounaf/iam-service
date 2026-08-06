<?php

namespace App\Controller;

use App\Entity\Membre;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MembreQrCodeController extends AbstractController
{
    #[Route('/api/membres/{id}/qr-code', name: 'api_membre_qrcode', methods: ['GET'])]
    public function __invoke(?Membre $membre): Response
    {
        if (!$membre) {
            throw new NotFoundHttpException('Membre non trouvé.');
        }

        $token = $membre->getQrCodeToken();
        if (!$token) {
            $token = 'N/A';
        }

        // Generate QR Code using endroid/qr-code
        $qrCode = new QrCode(
            data: $token,
            size: 300,
            margin: 10
        );

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return new Response(
            $result->getString(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=3600'
            ]
        );
    }
}
