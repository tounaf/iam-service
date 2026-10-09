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

        // Generate QR Code inline as base64
        $qrCode = new QrCode(
            data: $qrData,
            size: 160,
            margin: 5
        );
        $writer = new PngWriter();
        $qrCodeBase64 = base64_encode($writer->write($qrCode)->getString());

        // Get church, group/zone and associations details
        $fiangonanaNom = $membre->getFiangonana() ? $membre->getFiangonana()->getNom() : 'Paroisse non spécifiée';
        $groupeNom = $membre->getZoneGeographique() ? $membre->getZoneGeographique()->getNom() : 'Non spécifié';

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

        // Role assignments
        $rolesList = [];
        foreach ($membre->getRoleAssignments() as $ra) {
            $roleName = $ra->getRole()?->getNom() ?? 'Rôle';
            $contextLabel = 'Système';
            if ($ra->getAssociation()) {
                $contextLabel = 'Assoc: ' . $ra->getAssociation()->getNom();
            } elseif ($ra->getGroupe()) {
                $contextLabel = 'Groupe: ' . $ra->getGroupe()->getNom();
            } elseif ($ra->getFiangonana()) {
                $contextLabel = 'Paroisse: ' . $ra->getFiangonana()->getNom();
            }
            $rolesList[] = [
                'id' => $ra->getId(),
                'role' => $roleName,
                'context' => $contextLabel,
                'exerciceYear' => $ra->getExerciceYear()
            ];
        }

        // Attendance / Participation Statistics for current year
        $yearParam = $request->query->get('year');
        $year = $yearParam !== null ? (int)$yearParam : (int)date('Y');
        $stats = $this->attendanceStatsService->getMemberStats($membre, $year);

        $acceptHeader = $request->headers->get('Accept', '');
        $format = strtolower((string) $request->query->get('format', ''));

        if ($format === 'json' || str_contains($acceptHeader, 'application/json')) {
            return new JsonResponse([
                'id' => $membre->getId(),
                'memberId' => $membre->getId(),
                'nom' => $membre->getNom() ?? '',
                'prenom' => $membre->getPrenom() ?? '',
                'email' => $membre->getEmail() ?? '',
                'telephone' => $membre->getTelephone() ?? 'Non renseigné',
                'sexe' => $membre->getSexe(),
                'adresse' => $membre->getAdresse() ?? 'Non renseignée',
                'dateNaissance' => $membre->getDateNaissance()?->format('Y-m-d'),
                'age' => $membre->getAge(),
                'photoUrl' => $membre->getPhotoUrl(),
                'fiangonanaNom' => $fiangonanaNom,
                'groupeNom' => $groupeNom,
                'associations' => $associationsList,
                'associationsArray' => $associationsArray,
                'associationsStr' => $associationsStr,
                'roles' => $rolesList,
                'qrCodeToken' => $token,
                'qrCodeBase64' => $qrCodeBase64,
                'scanUrl' => $scanUrl,
                'participationStats' => $stats
            ], Response::HTTP_OK, [
                'Cache-Control' => 'public, max-age=3600'
            ]);
        }

        $html = $this->twig->render('membre/fiche.html.twig', [
            'membre' => $membre,
            'nom' => $membre->getNom() ?? '',
            'prenom' => $membre->getPrenom() ?? '',
            'email' => $membre->getEmail() ?? '',
            'telephone' => $membre->getTelephone() ?? 'Non renseigné',
            'sexe' => $membre->getSexe(),
            'adresse' => $membre->getAdresse() ?? 'Non renseignée',
            'dateNaissance' => $membre->getDateNaissance()?->format('d/m/Y'),
            'age' => $membre->getAge(),
            'photoUrl' => $membre->getPhotoUrl(),
            'fiangonanaNom' => $fiangonanaNom,
            'groupeNom' => $groupeNom,
            'associationsStr' => $associationsStr,
            'associationsList' => $associationsList,
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
