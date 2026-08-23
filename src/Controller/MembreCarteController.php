<?php

namespace App\Controller;

use App\Entity\Membre;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class MembreCarteController extends AbstractController
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    #[Route('/api/membres/{id}/carte', name: 'api_membre_carte', methods: ['GET'])]
    public function __invoke(?Membre $membre, ?Request $request = null): Response
    {
        if (!$membre) {
            throw new NotFoundHttpException('Membre non trouvé.');
        }

        if ($request === null) {
            $request = Request::createFromGlobals();
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
        $associationsArray = [];
        foreach ($membre->getAssociations() as $assoc) {
            $assocNom = $assoc->getNom() ?? '';
            $associationsList[] = $assocNom;
            $associationsArray[] = [
                'id' => $assoc->getId(),
                'nom' => $assocNom
            ];
        }
        $associationsStr = !empty($associationsList) ? implode(', ', $associationsList) : 'Aucune';

        $nom = $membre->getNom() ?? '';
        $prenom = $membre->getPrenom() ?? '';
        $email = $membre->getEmail() ?? '';
        $telephone = $membre->getTelephone() ?? 'Non renseigné';

        $format = $request->query->get('format');
        $acceptHeader = $request->headers->get('Accept', '');

        if ($format === 'json' || str_contains($acceptHeader, 'application/json')) {
            return new JsonResponse([
                'id' => $membre->getId(),
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone,
                'photoUrl' => $membre->getPhotoUrl(),
                'fiangonana' => [
                    'id' => $membre->getFiangonana()?->getId(),
                    'nom' => $fiangonanaNom
                ],
                'zoneGeographique' => [
                    'id' => $membre->getZoneGeographique()?->getId(),
                    'nom' => $groupeNom
                ],
                'associations' => $associationsArray,
                'qrCodeToken' => $token,
                'qrCodeUrl' => sprintf('/api/membres/%d/qr-code', $membre->getId()),
                'qrCodeBase64' => $qrCodeBase64
            ], Response::HTTP_OK, [
                'Cache-Control' => 'public, max-age=3600'
            ]);
        }

        $html = $this->twig->render('membre/carte.html.twig', [
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'fiangonanaNom' => $fiangonanaNom,
            'groupeNom' => $groupeNom,
            'associationsStr' => $associationsStr,
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
