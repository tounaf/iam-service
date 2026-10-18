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

class MembreFicheController extends AbstractController
{
    public function __construct(
        private Environment $twig,
        private AttendanceStatsService $attendanceStatsService
    ) {}

    #[Route('/api/membres/{id}/fiche', name: 'api_membre_fiche', methods: ['GET'])]
    public function __invoke(?Membre $membre, Request $request): Response
    {
        if (!$membre) {
            throw new NotFoundHttpException('Membre non trouvé.');
        }

        $yearParam = $request->query->get('year');
        $year = $yearParam !== null ? (int)$yearParam : (int)date('Y');

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

        // Get church and geographic zone details
        $fiangonanaNom = $membre->getFiangonana()?->getNom() ?? 'Paroisse';
        $groupeNom = $membre->getZoneGeographique()?->getNom() ?? 'Non spécifié';

        $associationsList = [];
        $associationsArray = [];
        foreach ($membre->getAssociations() as $assoc) {
            $assocNom = $assoc->getNom() ?? '';
            $associationsList[] = $assocNom;
            $associationsArray[] = [
                'id' => $assoc->getId(),
                'nom' => $assocNom,
            ];
        }
        $associationsStr = !empty($associationsList) ? implode(', ', $associationsList) : 'Aucune';

        // Get active role assignments
        $rolesList = [];
        if ($membre->getRoleAssignments()) {
            foreach ($membre->getRoleAssignments() as $assignment) {
                if ($assignment->getIsActive()) {
                    $contextName = 'Général';
                    if ($assignment->getAssociationContext()) {
                        $contextName = $assignment->getAssociationContext()->getNom();
                    } elseif ($assignment->getGroupeContext()) {
                        $contextName = $assignment->getGroupeContext()->getNom();
                    } elseif ($assignment->getFiangonanaContext()) {
                        $contextName = $assignment->getFiangonanaContext()->getNom();
                    }
                    $rolesList[] = [
                        'id' => $assignment->getId(),
                        'role' => $assignment->getRole()?->getName() ?? 'Membre',
                        'context' => $contextName,
                        'exerciceYear' => $assignment->getExerciceYear(),
                    ];
                }
            }
        }

        // Attendance stats
        $stats = $this->attendanceStatsService->getMemberStats($membre, $year);

        $nom = $membre->getNom() ?? '';
        $prenom = $membre->getPrenom() ?? '';
        $email = $membre->getEmail() ?? '';
        $telephone = $membre->getTelephone() ?? 'Non renseigné';
        $sexe = $membre->getSexe() ?? 'N/C';
        $adresse = $membre->getAdresse() ?? 'Non renseignée';
        $dateNaissance = $membre->getDateNaissance()?->format('d/m/Y') ?? 'Non renseignée';
        $photo = $membre->getPhotoUrl();

        $acceptHeader = $request->headers->get('Accept', '');
        $format = strtolower((string) $request->query->get('format', ''));

        if ($format === 'json' || str_contains($acceptHeader, 'application/json')) {
            return new JsonResponse([
                'id' => $membre->getId(),
                'memberId' => $membre->getId(),
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone,
                'sexe' => $sexe,
                'adresse' => $adresse,
                'dateNaissance' => $dateNaissance,
                'photo' => $photo,
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
            ], Response::HTTP_OK, [
                'Cache-Control' => 'public, max-age=3600'
            ]);
        }

        $html = $this->twig->render('membre/fiche.html.twig', [
            'memberId' => $membre->getId(),
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'sexe' => $sexe,
            'adresse' => $adresse,
            'dateNaissance' => $dateNaissance,
            'photo' => $photo,
            'fiangonanaNom' => $fiangonanaNom,
            'groupeNom' => $groupeNom,
            'associationsStr' => $associationsStr,
            'associationsList' => $associationsList,
            'roles' => $rolesList,
            'qrCodeBase64' => $qrCodeBase64,
            'token' => $token,
            'scanUrl' => $scanUrl,
            'year' => $year,
            'stats' => $stats,
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
