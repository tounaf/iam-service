<?php

namespace App\Controller;

use App\Entity\Membre;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class MembreCarteController extends AbstractController
{
    public function __construct(
        private Environment $twig
    ) {}

    #[Route('/api/membres/{id}/carte', name: 'api_membre_carte', methods: ['GET'])]
    public function __invoke(?Membre $membre): Response
    {
        if (!$membre) {
            throw new NotFoundHttpException('Membre non trouvé.');
        }

        $token = $membre->getQrCodeToken() ?: 'N/A';

        // Generate QR code inline as base64
        $qrCode = new QrCode(
            data: $token,
            size: 150,
            margin: 5
        );
        $writer = new PngWriter();
        $qrCodeBase64 = base64_encode($writer->write($qrCode)->getString());

        // Get church, group/zone and associations details
        $fiangonanaNom = $membre->getFiangonana() ? $membre->getFiangonana()->getNom() : 'Paroisse';
        $fiangonanaNom = $fiangonanaNom ?? 'Paroisse';

        $groupeNom = $membre->getZoneGeographique() ? $membre->getZoneGeographique()->getNom() : 'Non spécifié';
        $groupeNom = $groupeNom ?? 'Non spécifié';

        $associationsList = [];
        foreach ($membre->getAssociations() as $assoc) {
            $associationsList[] = $assoc->getNom() ?? '';
        }
        $associationsStr = !empty($associationsList) ? implode(', ', $associationsList) : 'Aucune';

        $nom = $membre->getNom() ?? '';
        $prenom = $membre->getPrenom() ?? '';
        $email = $membre->getEmail() ?? '';
        $telephone = $membre->getTelephone() ?? 'Non renseigné';

        // Render the Twig template using injected Twig environment
        $html = $this->twig->render('membre/carte.html.twig', [
            'nom' => $nom,
            'prenom' => $prenom,
            'fiangonanaNom' => $fiangonanaNom,
            'groupeNom' => $groupeNom,
            'associationsStr' => $associationsStr,
            'email' => $email,
            'telephone' => $telephone,
            'qrCodeBase64' => $qrCodeBase64,
            'memberId' => $membre->getId(),
        ]);

        return new Response(
            $html,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/html; charset=utf-8',
                'Cache-Control' => 'public, max-age=3600'
            ]
        );
    }
}
