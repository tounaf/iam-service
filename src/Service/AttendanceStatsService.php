<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Membre;
use App\Entity\Presence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AttendanceStatsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache
    ) {}

    public function getCacheKey(Membre $membre, int $year): string
    {
        return sprintf('member_attendance_stats_%d_%d', $membre->getId(), $year);
    }

    public function invalidateMemberCache(Membre $membre, ?int $year = null): void
    {
        if ($year !== null) {
            $this->cache->delete($this->getCacheKey($membre, $year));
        } else {
            $currentYear = (int)date('Y');
            $this->cache->delete($this->getCacheKey($membre, $currentYear));
            $this->cache->delete($this->getCacheKey($membre, $currentYear - 1));
            $this->cache->delete($this->getCacheKey($membre, $currentYear + 1));
        }
    }

    /**
     * Calculates and caches member attendance & delay stats for a given year.
     */
    public function getMemberStats(Membre $membre, int $year): array
    {
        $cacheKey = $this->getCacheKey($membre, $year);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($membre, $year) {
            // Keep until explicitly invalidated on new presence scan
            $item->expiresAfter(null);

            return $this->calculateMemberStats($membre, $year);
        });
    }

    public function calculateMemberStats(Membre $membre, int $year): array
    {
        $startDate = new \DateTimeImmutable("$year-01-01 00:00:00");
        $endDate = new \DateTimeImmutable("$year-12-31 23:59:59");

        // 1. All distinct activities across system in that year
        $allActivitiesResult = $this->entityManager->createQuery(
            'SELECT DISTINCT p.activityName FROM App\Entity\Presence p WHERE p.scannedAt >= :startDate AND p.scannedAt <= :endDate'
        )->setParameters([
            'startDate' => $startDate,
            'endDate' => $endDate
        ])->getScalarResult();

        $allActivities = array_filter(array_column($allActivitiesResult, 'activityName'));

        // 2. Member distinct activities
        $memberActivitiesResult = $this->entityManager->createQuery(
            'SELECT DISTINCT p.activityName FROM App\Entity\Presence p WHERE p.membre = :membre AND p.scannedAt >= :startDate AND p.scannedAt <= :endDate'
        )->setParameters([
            'membre' => $membre,
            'startDate' => $startDate,
            'endDate' => $endDate
        ])->getScalarResult();

        $memberActivities = array_filter(array_column($memberActivitiesResult, 'activityName'));

        // 3. Member presence logs
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

        // Load events to check event start times and relevance to member
        $events = $this->entityManager->getRepository(Evenement::class)->createQueryBuilder('e')
            ->where('e.createdAt >= :startDate OR e.dateDebut >= :startDate')
            ->setParameter('startDate', $startDate)
            ->getQuery()
            ->getResult();

        $memberAssocIds = [];
        foreach ($membre->getAssociations() as $assoc) {
            $memberAssocIds[] = $assoc->getId();
        }
        $memberGroupeId = $membre->getZoneGeographique()?->getId();
        $memberFiangonanaId = $membre->getFiangonana()?->getId();

        $assocStats = [];
        foreach ($membre->getAssociations() as $a) {
            $assocStats[$a->getId()] = [
                'id' => $a->getId(),
                'nom' => $a->getNom(),
                'totalEvents' => 0,
                'attended' => 0,
                'late' => 0,
                'absent' => 0,
                'presenceRate' => 0.0,
                'absenceRate' => 0.0,
            ];
        }

        $groupeStat = null;
        if ($membre->getZoneGeographique()) {
            $g = $membre->getZoneGeographique();
            $groupeStat = [
                'id' => $g->getId(),
                'nom' => $g->getNom(),
                'totalEvents' => 0,
                'attended' => 0,
                'late' => 0,
                'absent' => 0,
                'presenceRate' => 0.0,
                'absenceRate' => 0.0,
            ];
        }

        $fiangonanaStat = null;
        if ($membre->getFiangonana()) {
            $f = $membre->getFiangonana();
            $fiangonanaStat = [
                'id' => $f->getId(),
                'nom' => $f->getNom(),
                'totalEvents' => 0,
                'attended' => 0,
                'late' => 0,
                'absent' => 0,
                'presenceRate' => 0.0,
                'absenceRate' => 0.0,
            ];
        }

        $eventsMap = [];
        $relevantActivitiesForMember = [];
        $now = new \DateTime();

        foreach ($events as $evt) {
            $isRelevant = false;
            $contextType = 'global';
            $assocId = null;

            if ($evt->getAssociation()) {
                if (in_array($evt->getAssociation()->getId(), $memberAssocIds, true)) {
                    $isRelevant = true;
                    $contextType = 'association';
                    $assocId = $evt->getAssociation()->getId();
                }
            } elseif ($evt->getGroupe()) {
                if ($memberGroupeId && $evt->getGroupe()->getId() === $memberGroupeId) {
                    $isRelevant = true;
                    $contextType = 'groupe';
                }
            } elseif ($evt->getFiangonana()) {
                if ($memberFiangonanaId && $evt->getFiangonana()->getId() === $memberFiangonanaId) {
                    $isRelevant = true;
                    $contextType = 'fiangonana';
                }
            } else {
                $isRelevant = true;
            }

            if ($isRelevant && $evt->getNom()) {
                $eventsMap[$evt->getNom()] = $evt;
                $relevantActivitiesForMember[] = $evt->getNom();

                if (!$evt->getDateDebut() || $evt->getDateDebut() <= $now) {
                    $pMatch = null;
                    foreach ($presences as $p) {
                        if ($p->getActivityName() === $evt->getNom()) {
                            $pMatch = $p;
                            break;
                        }
                    }

                    $isLateEvt = false;
                    if ($pMatch && $evt->getDateDebut() && $pMatch->getScannedAt()) {
                        if ($pMatch->getScannedAt()->getTimestamp() > $evt->getDateDebut()->getTimestamp()) {
                            $isLateEvt = true;
                        }
                    }

                    if ($contextType === 'association' && $assocId && isset($assocStats[$assocId])) {
                        $assocStats[$assocId]['totalEvents']++;
                        if ($pMatch) {
                            $assocStats[$assocId]['attended']++;
                            if ($isLateEvt) {
                                $assocStats[$assocId]['late']++;
                            }
                        } else {
                            $assocStats[$assocId]['absent']++;
                        }
                    } elseif ($contextType === 'groupe' && $groupeStat) {
                        $groupeStat['totalEvents']++;
                        if ($pMatch) {
                            $groupeStat['attended']++;
                            if ($isLateEvt) {
                                $groupeStat['late']++;
                            }
                        } else {
                            $groupeStat['absent']++;
                        }
                    } elseif ($contextType === 'fiangonana' && $fiangonanaStat) {
                        $fiangonanaStat['totalEvents']++;
                        if ($pMatch) {
                            $fiangonanaStat['attended']++;
                            if ($isLateEvt) {
                                $fiangonanaStat['late']++;
                            }
                        } else {
                            $fiangonanaStat['absent']++;
                        }
                    }
                }
            }
        }

        foreach ($assocStats as &$aStat) {
            $tot = $aStat['totalEvents'];
            $aStat['presenceRate'] = $tot > 0 ? round(($aStat['attended'] / $tot) * 100, 1) : 0.0;
            $aStat['absenceRate'] = $tot > 0 ? round(($aStat['absent'] / $tot) * 100, 1) : 0.0;
        }
        unset($aStat);

        if ($groupeStat) {
            $tot = $groupeStat['totalEvents'];
            $groupeStat['presenceRate'] = $tot > 0 ? round(($groupeStat['attended'] / $tot) * 100, 1) : 0.0;
            $groupeStat['absenceRate'] = $tot > 0 ? round(($groupeStat['absent'] / $tot) * 100, 1) : 0.0;
        }

        if ($fiangonanaStat) {
            $tot = $fiangonanaStat['totalEvents'];
            $fiangonanaStat['presenceRate'] = $tot > 0 ? round(($fiangonanaStat['attended'] / $tot) * 100, 1) : 0.0;
            $fiangonanaStat['absenceRate'] = $tot > 0 ? round(($fiangonanaStat['absent'] / $tot) * 100, 1) : 0.0;
        }

        $allActivities = array_unique($relevantActivitiesForMember);

        $presenceDetails = [];
        $lateCount = 0;
        $onTimeCount = 0;

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
                } else {
                    $onTimeCount++;
                }
            } else {
                $onTimeCount++;
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
        $onTimeRate = $attendedActivitiesCount > 0 ? round(($onTimeCount / $attendedActivitiesCount) * 100, 2) : 0.0;

        return [
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
            'onTimeCount' => $onTimeCount,
            'lateRate' => $lateRate,
            'onTimeRate' => $onTimeRate,
            'allActivitiesInYear' => array_values($allActivities),
            'attendedActivitiesInYear' => array_values($memberActivities),
            'presenceLogs' => $presenceDetails,
            'entityStats' => [
                'associations' => array_values($assocStats),
                'groupe' => $groupeStat,
                'fiangonana' => $fiangonanaStat,
            ],
        ];
    }
}
