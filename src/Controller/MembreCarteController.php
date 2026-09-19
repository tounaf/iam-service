<?php

namespace App\Controller;

use App\Entity\Membre;
use App\Service\AttendanceStatsService;
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
    public function __construct(
        private Environment $twig,
        private ?AttendanceStatsService $attendanceStatsService = null
    ) {}

    #[Route('/api/membres/{id}/carte', name: 'api_membre_carte', methods: ['GET'])]
    #[Route('/api/membres/{id}/fiche', name: 'api_membre_fiche', methods: ['GET'])]
    public function __invoke(?Membre $membre, ?Request $request = null): Response
    {
        if (!$membre) {
            throw new NotFoundHttpException('Membre non trouvé.');
        }

        if ($request === null) {
            $request = Request::createFromGlobals();
        }

        $token = $membre->getQrCodeToken() ?: 'N/A';
        $host = $request->getSchemeAndHttpHost() ?: 'http://localhost';
        $scanUrl = $token !== 'N/A' ? sprintf('%s/membres/scan/%s', $host, $token) : 'N/A';

        $raw = $request->query->get('raw');
        $qrData = ($raw === '1' || $raw === 'true') ? $token : $scanUrl;

        // Generate QR code inline as base64
        $qrCode = new QrCode(
            data: $qrData,
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
        $adresse = $membre->getAdresse() ?? 'Non renseignée';
        $dateNaissance = $membre->getDateNaissance()?->format('d/m/Y') ?? 'Non renseignée';
        $age = $membre->getAge();
        $photoUrl = $membre->getPhotoUrl();

        // Roles and mandats list
        $rolesList = [];
        if ($membre->getRoleAssignments()) {
            foreach ($membre->getRoleAssignments() as $ra) {
                if ($ra->getIsActive() && $ra->getRole()) {
                    $contextName = 'Paroisse';
                    if ($ra->getAssociationContext()) {
                        $contextName = 'Association: ' . $ra->getAssociationContext()->getNom();
                    } elseif ($ra->getGroupeContext()) {
                        $contextName = 'Zone: ' . $ra->getGroupeContext()->getNom();
                    } elseif ($ra->getSousGroupeContext()) {
                        $contextName = 'Sous-groupe: ' . $ra->getSousGroupeContext()->getNom();
                    }

                    $rolesList[] = [
                        'id' => $ra->getId(),
                        'role' => $ra->getRole()->getNom(),
                        'code' => $ra->getRole()->getCode(),
                        'context' => $contextName,
                        'exerciceYear' => $ra->getExerciceYear(),
                    ];
                }
            }
        }

        // Attendance stats
        $yearParam = $request->query->get('year');
        $year = $yearParam !== null ? (int)$yearParam : (int)date('Y');
        $stats = null;
        if ($this->attendanceStatsService) {
            $stats = $this->attendanceStatsService->getMemberStats($membre, $year);
        }

        $acceptHeader = $request->headers->get('Accept', '');
        $format = strtolower((string) $request->query->get('format', ''));
        $isJsonRequest = $format === 'json' || str_contains($acceptHeader, 'application/json');

        $isFicheRoute = str_contains($request->getPathInfo(), '/fiche') || $request->attributes->get('_route') === 'api_membre_fiche';

        if ($isJsonRequest) {
            $responseData = [
                'id' => $membre->getId(),
                'memberId' => $membre->getId(),
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone,
                'adresse' => $adresse,
                'dateNaissance' => $dateNaissance,
                'age' => $age,
                'photoUrl' => $photoUrl,
                'fiangonanaNom' => $fiangonanaNom,
                'groupeNom' => $groupeNom,
                'associations' => $associationsList,
                'associationsArray' => $associationsArray,
                'associationsStr' => $associationsStr,
                'roles' => $rolesList,
                'qrCodeToken' => $token,
                'qrCodeBase64' => $qrCodeBase64,
                'scanUrl' => $scanUrl,
                'participationStats' => $stats,
            ];

            return new JsonResponse($responseData, Response::HTTP_OK, [
                'Cache-Control' => 'public, max-age=3600'
            ]);
        }

        $templateName = $isFicheRoute ? 'membre/fiche.html.twig' : 'membre/carte.html.twig';

        $html = $this->twig->render($templateName, [
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'adresse' => $adresse,
            'dateNaissance' => $dateNaissance,
            'age' => $age,
            'photoUrl' => $photoUrl,
            'fiangonanaNom' => $fiangonanaNom,
            'groupeNom' => $groupeNom,
            'associationsList' => $associationsList,
            'associationsStr' => $associationsStr,
            'rolesList' => $rolesList,
            'qrCodeBase64' => $qrCodeBase64,
            'memberId' => $membre->getId(),
            'token' => $token,
            'scanUrl' => $scanUrl,
            'stats' => $stats,
            'year' => $year,
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
