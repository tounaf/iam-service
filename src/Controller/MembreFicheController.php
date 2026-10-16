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
    private Environment $twig;
    private AttendanceStatsService $attendanceStatsService;

    public function __construct(Environment $twig, AttendanceStatsService $attendanceStatsService)
    {
        $this->twig = $twig;
        $this->attendanceStatsService = $attendanceStatsService;
    }

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

        // Generate QR code base64 string
        $qrCode = new QrCode(
            data: $qrData,
            size: 160,
            margin: 5
        );
        $writer = new PngWriter();
        $qrCodeBase64 = base64_encode($writer->write($qrCode)->getString());

        // Affiliations
        $fiangonanaNom = $membre->getFiangonana() ? $membre->getFiangonana()->getNom() : 'Paroisse non spécifiée';
        $fiangonanaNom = $fiangonanaNom ?? 'Paroisse non spécifiée';

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

        // Role Assignments
        $roleAssignmentsArray = [];
        foreach ($membre->getRoleAssignments() as $ra) {
            $roleName = 'Rôle non spécifié';
            if ($ra->getRole()) {
                if (method_exists($ra->getRole(), 'getName') && $ra->getRole()->getName() !== null) {
                    $roleName = $ra->getRole()->getName();
                } elseif (method_exists($ra->getRole(), 'getNom') && $ra->getRole()->getNom() !== null) {
                    $roleName = $ra->getRole()->getNom();
                }
            }
            $contextType = 'Global';
            $contextName = 'Paroisse';

            if ($ra->getAssociationContext()) {
                $contextType = 'Association';
                $contextName = $ra->getAssociationContext()->getNom();
            } elseif ($ra->getGroupeContext()) {
                $contextType = 'Zone/Groupe';
                $contextName = $ra->getGroupeContext()->getNom();
            } elseif ($ra->getFiangonanaContext()) {
                $contextType = 'Paroisse';
                $contextName = $ra->getFiangonanaContext()->getNom();
            }

            $roleAssignmentsArray[] = [
                'id' => $ra->getId(),
                'roleName' => $roleName,
                'contextType' => $contextType,
                'contextName' => $contextName,
                'exerciceYear' => $ra->getExerciceYear(),
                'startDate' => $ra->getStartDate()?->format('d/m/Y'),
                'endDate' => $ra->getEndDate()?->format('d/m/Y'),
                'isActive' => $ra->getIsActive(),
            ];
        }

        // Attendance stats for current year
        $currentYear = (int)date('Y');
        $stats = $this->attendanceStatsService->getMemberStats($membre, $currentYear);

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
                'roleAssignments' => $roleAssignmentsArray,
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
            'memberId' => $membre->getId(),
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
            'roleAssignments' => $roleAssignmentsArray,
            'qrCodeBase64' => $qrCodeBase64,
            'token' => $token,
            'scanUrl' => $scanUrl,
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
