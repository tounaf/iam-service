<?php

namespace App\Controller;

use App\Entity\Membre;
use App\Entity\Presence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MembreParticipationController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/api/membres/{id}/participation-stats', name: 'api_membre_participation_stats', methods: ['GET'])]
    public function __invoke(?Membre $membre, Request $request): JsonResponse
    {
        if (!$membre) {
            throw new NotFoundHttpException('Membre non trouvé.');
        }

        // Get optional year parameter, defaults to current year
        $yearParam = $request->query->get('year');
        $year = $yearParam !== null ? (int)$yearParam : (int)date('Y');

        $startDate = new \DateTimeImmutable("$year-01-01 00:00:00");
        $endDate = new \DateTimeImmutable("$year-12-31 23:59:59");

        // 1. Find all distinct activity names across the church/system for that year
        $allActivitiesResult = $this->entityManager->createQuery(
            'SELECT DISTINCT p.activityName FROM App\Entity\Presence p WHERE p.scannedAt >= :startDate AND p.scannedAt <= :endDate'
        )->setParameters([
            'startDate' => $startDate,
            'endDate' => $endDate
        ])->getScalarResult();

        $allActivities = array_filter(array_column($allActivitiesResult, 'activityName'));

        // 2. Find distinct activity names that this specific member attended in that year
        $memberActivitiesResult = $this->entityManager->createQuery(
            'SELECT DISTINCT p.activityName FROM App\Entity\Presence p WHERE p.membre = :membre AND p.scannedAt >= :startDate AND p.scannedAt <= :endDate'
        )->setParameters([
            'membre' => $membre,
            'startDate' => $startDate,
            'endDate' => $endDate
        ])->getScalarResult();

        $memberActivities = array_filter(array_column($memberActivitiesResult, 'activityName'));

        // 3. Fetch detailed presence logs for the member in that year
        $presences = $this->entityManager->getRepository(Presence::class)->createQueryBuilder('p')
            ->where('p.membre = :membre')
            ->andWhere('p.scannedAt >= :startDate')
            ->andWhere('p.scannedAt <= :endDate')
            ->orderBy('p.scannedAt', 'DESC')
            ->setParameter('membre', $membre)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        $presenceDetails = [];
        $lateCount = 0;

        // Load events for this year to cross-reference event start times with scannedAt timestamps
        $events = $this->entityManager->getRepository(\App\Entity\Evenement::class)->createQueryBuilder('e')
            ->where('e.createdAt >= :startDate OR e.dateDebut >= :startDate')
            ->setParameter('startDate', $startDate)
            ->getQuery()
            ->getResult();

        $eventsMap = [];
        foreach ($events as $evt) {
            if ($evt->getNom()) {
                $eventsMap[$evt->getNom()] = $evt;
            }
        }

        foreach ($presences as $p) {
            $isLate = false;
            $delayMinutes = 0;
            $evt = $eventsMap[$p->getActivityName()] ?? null;

            if ($evt && $evt->getDateDebut()) {
                $startTs = $evt->getDateDebut()->getTimestamp();
                $scanTs = $p->getScannedAt()->getTimestamp();

                if ($scanTs > $startTs) {
                    $isLate = true;
                    $delayMinutes = (int) ceil(($scanTs - $startTs) / 60);
                    $lateCount++;
                }
            }

            $presenceDetails[] = [
                'id' => $p->getId(),
                'activityName' => $p->getActivityName(),
                'scannedAt' => $p->getScannedAt()->format(\DateTimeInterface::ATOM),
                'isLate' => $isLate,
                'delayMinutes' => $delayMinutes,
                'scannedBy' => $p->getScannedBy() ? [
                    'id' => $p->getScannedBy()->getId(),
                    'nom' => $p->getScannedBy()->getNom(),
                    'prenom' => $p->getScannedBy()->getPrenom()
                ] : null
            ];
        }

        $totalActivitiesCount = count($allActivities);
        $attendedActivitiesCount = count($memberActivities);
        $participationRate = $totalActivitiesCount > 0 ? round(($attendedActivitiesCount / $totalActivitiesCount) * 100, 2) : 0.0;
        $lateRate = $attendedActivitiesCount > 0 ? round(($lateCount / $attendedActivitiesCount) * 100, 2) : 0.0;

        return new JsonResponse([
            'membre' => [
                'id' => $membre->getId(),
                'nom' => $membre->getNom(),
                'prenom' => $membre->getPrenom(),
                'email' => $membre->getEmail()
            ],
            'year' => $year,
            'totalActivitiesCount' => $totalActivitiesCount,
            'attendedActivitiesCount' => $attendedActivitiesCount,
            'participationRate' => $participationRate,
            'lateCount' => $lateCount,
            'lateRate' => $lateRate,
            'allActivitiesInYear' => array_values($allActivities),
            'attendedActivitiesInYear' => array_values($memberActivities),
            'presenceLogs' => $presenceDetails
        ], JsonResponse::HTTP_OK);
    }
}
