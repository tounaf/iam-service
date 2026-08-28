<?php

namespace App\Controller;

use App\Entity\Membre;
use App\Service\AttendanceStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MembreParticipationController extends AbstractController
{
    public function __construct(
        private AttendanceStatsService $attendanceStatsService
    ) {}

    #[Route('/api/membres/{id}/participation-stats', name: 'api_membre_participation_stats', methods: ['GET'])]
    public function __invoke(?Membre $membre, Request $request): JsonResponse
    {
        if (!$membre) {
            throw new NotFoundHttpException('Membre non trouvé.');
        }

        $yearParam = $request->query->get('year');
        $year = $yearParam !== null ? (int)$yearParam : (int)date('Y');

        $stats = $this->attendanceStatsService->getMemberStats($membre, $year);

        return new JsonResponse($stats, JsonResponse::HTTP_OK);
    }
}
