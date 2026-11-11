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
        private ?AttendanceStatsService $attendanceStatsService = null
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

        // Generate QR code inline as base64
        $qrCode = new QrCode(
            data: $qrData,
            size: 160,
            margin: 5
        );
        $writer = new PngWriter();
        $qrCodeBase64 = base64_encode($writer->write($qrCode)->getString());

        // Paroisse, Groupe, Associations
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

        // Mandats / Role assignments
        $rolesList = [];
        foreach ($membre->getRoleAssignments() as $ra) {
            if ($ra->getIsActive()) {
                $roleName = $ra->getRole() ? $ra->getRole()->getName() : 'Rôle';
                $context = 'Global';
                if ($ra->getAssociationContext()) {
                    $context = 'Assoc: ' . $ra->getAssociationContext()->getNom();
                } elseif ($ra->getGroupeContext()) {
                    $context = 'Groupe: ' . $ra->getGroupeContext()->getNom();
                } elseif ($ra->getFiangonanaContext()) {
                    $context = 'Paroisse: ' . $ra->getFiangonanaContext()->getNom();
                }
                $rolesList[] = [
                    'role' => $roleName,
                    'context' => $context,
                    'exerciceYear' => $ra->getExerciceYear(),
                ];
            }
        }

        $nom = $membre->getNom() ?? '';
        $prenom = $membre->getPrenom() ?? '';
        $email = $membre->getEmail() ?? '';
        $telephone = $membre->getTelephone() ?? 'Non renseigné';
        $adresse = $membre->getAdresse() ?? 'Non renseignée';
        $sexe = $membre->getSexe() ?? 'Non précisé';
        $age = $membre->getAge();
        $photoUrl = $membre->getPhotoUrl();

        // Participation stats calculation for current year
        $yearParam = $request->query->get('year');
        $year = $yearParam !== null ? (int)$yearParam : (int)date('Y');

        $stats = null;
        if ($this->attendanceStatsService) {
            try {
                $stats = $this->attendanceStatsService->getMemberStats($membre, $year);
            } catch (\Throwable $e) {
                // Silently fallback if error
                $stats = null;
            }
        }

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
                'adresse' => $adresse,
                'sexe' => $sexe,
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
                'stats' => $stats,
            ], Response::HTTP_OK, [
                'Cache-Control' => 'public, max-age=1800'
            ]);
        }

        $html = $this->twig->render('membre/fiche.html.twig', [
            'membre' => $membre,
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'adresse' => $adresse,
            'sexe' => $sexe,
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
            'year' => $year,
            'stats' => $stats,
        ]);

        return new Response(
            $html,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/html; charset=utf-8',
                'Cache-Control' => 'public, max-age=1800'
            ]
        );
    }
}
