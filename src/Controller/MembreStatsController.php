<?php

namespace App\Controller;

use App\Entity\Membre;
use App\Entity\Presence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MembreStatsController extends AbstractController
{
    #[Route('/api/membres/{id}/stats', name: 'api_membre_stats', methods: ['GET'])]
    public function __invoke(?Membre $membre, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$membre) {
            throw new NotFoundHttpException('Membre non trouvé.');
        }

        // Fetch all presences for this member
        $presences = $entityManager->getRepository(Presence::class)->findBy(
            ['membre' => $membre],
            ['scannedAt' => 'DESC']
        );

        $totalPresences = count($presences);

        // Group presences by activity name
        $presencesByActivity = [];
        $recentPresences = [];

        foreach ($presences as $presence) {
            $activityName = $presence->getActivityName();
            if (!isset($presencesByActivity[$activityName])) {
                $presencesByActivity[$activityName] = 0;
            }
            $presencesByActivity[$activityName]++;

            $recentPresences[] = [
                'id' => $presence->getId(),
                'activityName' => $presence->getActivityName(),
                'scannedAt' => $presence->getScannedAt()->format(\DateTimeInterface::ATOM),
                'scannedBy' => $presence->getScannedBy() ? [
                    'id' => $presence->getScannedBy()->getId(),
                    'nom' => $presence->getScannedBy()->getNom(),
                    'prenom' => $presence->getScannedBy()->getPrenom()
                ] : null
            ];
        }

        // Calculate dynamic participation rate based on overall unique activities in the entire system
        $allUniqueActivities = $entityManager->getRepository(Presence::class)
            ->createQueryBuilder('p')
            ->select('DISTINCT p.activityName')
            ->getQuery()
            ->getSingleColumnResult();

        $totalUniqueActivitiesCount = count($allUniqueActivities);
        $memberUniqueActivitiesCount = count(array_keys($presencesByActivity));

        $participationRate = $totalUniqueActivitiesCount > 0
            ? round(($memberUniqueActivitiesCount / $totalUniqueActivitiesCount) * 100, 2)
            : 0.0;

        return new JsonResponse([
            'membre' => [
                'id' => $membre->getId(),
                'nom' => $membre->getNom(),
                'prenom' => $membre->getPrenom(),
                'email' => $membre->getEmail(),
                'fiangonana' => $membre->getFiangonana() ? [
                    'id' => $membre->getFiangonana()->getId(),
                    'nom' => $membre->getFiangonana()->getNom()
                ] : null
            ],
            'statistics' => [
                'totalPresences' => $totalPresences,
                'totalUniqueSystemActivities' => $totalUniqueActivitiesCount,
                'memberUniqueActivitiesCount' => $memberUniqueActivitiesCount,
                'participationRate' => $participationRate,
                'presencesByActivity' => $presencesByActivity
            ],
            'presences' => $recentPresences
        ], Response::HTTP_OK);
    }
}
