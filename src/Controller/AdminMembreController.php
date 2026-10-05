<?php

namespace App\Controller;

use App\Entity\Association;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Membre;
use App\Entity\Presence;
use App\Entity\Role;
use App\Entity\RoleAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AdminMembreController extends AbstractController
{
    #[Route('/admin/membres', name: 'admin_membre_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $fiangonanaId = $request->query->get('fiangonana') ? (int) $request->query->get('fiangonana') : null;
        $groupeId = $request->query->get('groupe') ? (int) $request->query->get('groupe') : null;
        $associationId = $request->query->get('association') ? (int) $request->query->get('association') : null;
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 50;

        /** @var \App\Repository\MembreRepository $membreRepo */
        $membreRepo = $em->getRepository(Membre::class);
        $paginator = $membreRepo->findBySearchAndPaginate(
            $search !== '' ? $search : null,
            $fiangonanaId,
            $groupeId,
            $associationId,
            $page,
            $limit
        );

        $totalItems = count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / $limit));

        $fiangonanas = $em->getRepository(Fiangonana::class)->findAll();
        $groupes = $em->getRepository(Groupe::class)->findAll();
        $associations = $em->getRepository(Association::class)->findAll();

        return $this->render('admin/membres/index.html.twig', [
            'current_route' => 'admin_membre',
            'membres' => $paginator,
            'totalItems' => $totalItems,
            'page' => $page,
            'totalPages' => $totalPages,
            'limit' => $limit,
            'fiangonanas' => $fiangonanas,
            'groupes' => $groupes,
            'associations' => $associations,
            'filters' => [
                'search' => $search,
                'fiangonana' => $fiangonanaId,
                'groupe' => $groupeId,
                'association' => $associationId,
            ],
        ]);
    }

    #[Route('/admin/membres/nouveau', name: 'admin_membre_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom', ''));
            $prenom = trim($request->request->get('prenom', ''));
            $email = trim($request->request->get('email', ''));
            $telephone = trim($request->request->get('telephone', ''));
            $dateNaissanceStr = trim($request->request->get('dateNaissance', ''));
            $photoUrl = trim($request->request->get('photoUrl', ''));
            $groupeId = $request->request->get('groupe_id');
            $fiangonanaId = $request->request->get('fiangonana_id');
            $associationIds = $request->request->all('association_ids');

            $membre = new Membre();
            $membre->setNom($nom);
            $membre->setPrenom($prenom);
            $membre->setEmail($email ?: null);
            $membre->setTelephone($telephone ?: null);
            $membre->setDateNaissance($dateNaissanceStr ? new \DateTime($dateNaissanceStr) : null);
            $membre->setPhotoUrl($photoUrl ?: null);

            if ($groupeId) {
                $groupe = $em->getRepository(Groupe::class)->find($groupeId);
                if ($groupe) {
                    $membre->setZoneGeographique($groupe);
                }
            }

            if ($fiangonanaId) {
                $fiangonana = $em->getRepository(Fiangonana::class)->find($fiangonanaId);
                if ($fiangonana) {
                    $membre->setFiangonana($fiangonana);
                }
            }

            if (!empty($associationIds)) {
                foreach ($associationIds as $assocId) {
                    $assoc = $em->getRepository(Association::class)->find($assocId);
                    if ($assoc) {
                        $membre->addAssociation($assoc);
                    }
                }
            }

            $em->persist($membre);
            $em->flush();

            $this->addFlash('success', sprintf('Membre %s %s inscrit avec succès !', $prenom, $nom));

            return $this->redirectToRoute('admin_membre_edit', ['id' => $membre->getId()]);
        }

        $groupes = $em->getRepository(Groupe::class)->findAll();
        $fiangonanas = $em->getRepository(Fiangonana::class)->findAll();
        $associations = $em->getRepository(Association::class)->findAll();
        $roles = $em->getRepository(Role::class)->findAll();

        return $this->render('admin/membres/form.html.twig', [
            'current_route' => 'admin_membre',
            'isEdit' => false,
            'membre' => null,
            'groupes' => $groupes,
            'fiangonanas' => $fiangonanas,
            'associations' => $associations,
            'roles' => $roles,
            'presences' => [],
        ]);
    }

    #[Route('/admin/membres/{id}/editer', name: 'admin_membre_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $membre = $em->getRepository(Membre::class)->find($id);
        if (!$membre) {
            throw new NotFoundHttpException('Membre introuvable.');
        }

        if ($request->isMethod('POST')) {
            $action = $request->request->get('form_action');

            if ($action === 'add_role') {
                $roleId = $request->request->get('role_id');
                $contextType = $request->request->get('context_type');
                $contextId = $request->request->get('context_id');
                $startDateStr = $request->request->get('start_date');
                $endDateStr = $request->request->get('end_date');
                $exerciceYear = trim($request->request->get('exercice_year', ''));

                $role = $em->getRepository(Role::class)->find($roleId);

                if ($role) {
                    $assignment = new RoleAssignment();
                    $assignment->setMembre($membre);
                    $assignment->setRole($role);

                    $startDate = $startDateStr ? new \DateTimeImmutable($startDateStr) : new \DateTimeImmutable();
                    $assignment->setStartDate($startDate);

                    if ($endDateStr) {
                        $assignment->setEndDate(new \DateTimeImmutable($endDateStr));
                    }

                    $assignment->setExerciceYear($exerciceYear ?: $startDate->format('Y'));
                    $assignment->setIsActive(true);

                    if ($contextType === 'association' && $contextId) {
                        $assocContext = $em->getRepository(Association::class)->find($contextId);
                        $assignment->setAssociationContext($assocContext);
                    } elseif ($contextType === 'groupe' && $contextId) {
                        $groupeContext = $em->getRepository(Groupe::class)->find($contextId);
                        $assignment->setGroupeContext($groupeContext);
                    } elseif ($contextType === 'fiangonana' && $contextId) {
                        $fiangonanaContext = $em->getRepository(Fiangonana::class)->find($contextId);
                        $assignment->setFiangonanaContext($fiangonanaContext);
                    } elseif ($membre->getFiangonana()) {
                        $assignment->setFiangonanaContext($membre->getFiangonana());
                    }

                    $em->persist($assignment);
                    $em->flush();

                    $this->addFlash('success', sprintf('Rôle "%s" attribué à %s.', $role->getName(), $membre->getPrenom()));
                }
            } elseif ($action === 'update_associations') {
                $associationIds = $request->request->all('association_ids');

                // Clear current associations
                foreach ($membre->getAssociations() as $existingAssoc) {
                    $membre->removeAssociation($existingAssoc);
                }

                if (!empty($associationIds)) {
                    foreach ($associationIds as $assocId) {
                        $assoc = $em->getRepository(Association::class)->find($assocId);
                        if ($assoc) {
                            $membre->addAssociation($assoc);
                        }
                    }
                }

                $em->flush();

                $this->addFlash('success', sprintf('Associations de %s mises à jour avec succès !', $membre->getPrenom()));
            } else {
                $nom = trim($request->request->get('nom', ''));
                $prenom = trim($request->request->get('prenom', ''));
                $email = trim($request->request->get('email', ''));
                $telephone = trim($request->request->get('telephone', ''));
                $dateNaissanceStr = trim($request->request->get('dateNaissance', ''));
                $photoUrl = trim($request->request->get('photoUrl', ''));
                $groupeId = $request->request->get('groupe_id');
                $fiangonanaId = $request->request->get('fiangonana_id');

                $membre->setNom($nom);
                $membre->setPrenom($prenom);
                $membre->setEmail($email ?: null);
                $membre->setTelephone($telephone ?: null);
                $membre->setDateNaissance($dateNaissanceStr ? new \DateTime($dateNaissanceStr) : null);
                $membre->setPhotoUrl($photoUrl ?: null);

                if ($groupeId) {
                    $groupe = $em->getRepository(Groupe::class)->find($groupeId);
                    $membre->setZoneGeographique($groupe);
                } else {
                    $membre->setZoneGeographique(null);
                }

                if ($fiangonanaId) {
                    $fiangonana = $em->getRepository(Fiangonana::class)->find($fiangonanaId);
                    $membre->setFiangonana($fiangonana);
                }

                $em->flush();

                $this->addFlash('success', sprintf('Membre %s %s mis à jour avec succès !', $prenom, $nom));
            }

            return $this->redirectToRoute('admin_membre_edit', ['id' => $membre->getId()]);
        }

        $groupes = $em->getRepository(Groupe::class)->findAll();
        $fiangonanas = $em->getRepository(Fiangonana::class)->findAll();
        $associations = $em->getRepository(Association::class)->findAll();
        $roles = $em->getRepository(Role::class)->findAll();
        $presences = $em->getRepository(Presence::class)->findBy(['membre' => $membre], ['scannedAt' => 'DESC']);

        // Retrieve all past or current system events (dateDebut <= now or dateDebut null) for attendance rate calculation
        $now = new \DateTime();
        $allEvents = $em->getRepository(\App\Entity\Evenement::class)->findBy([], ['dateDebut' => 'DESC']);

        // Identify member affiliations
        $memberAssocIds = [];
        foreach ($membre->getAssociations() as $assoc) {
            $memberAssocIds[] = $assoc->getId();
        }
        $memberGroupeId = $membre->getZoneGeographique()?->getId();
        $memberFiangonanaId = $membre->getFiangonana()?->getId();

        // Helper function to find best matching event for a presence scan
        $findBestMatchingEvent = function (Presence $p, array $eventsList): ?\App\Entity\Evenement {
            if (!$p->getActivityName()) {
                return null;
            }

            $candidates = [];
            foreach ($eventsList as $e) {
                if ($e->getNom() === $p->getActivityName()) {
                    $candidates[] = $e;
                }
            }

            if (empty($candidates)) {
                return null;
            }

            if (count($candidates) === 1) {
                return $candidates[0];
            }

            // Find closest candidate by dateDebut to scan timestamp
            $scanTs = $p->getScannedAt()?->getTimestamp() ?? 0;
            $bestCandidate = null;
            $minDiff = PHP_INT_MAX;

            foreach ($candidates as $cand) {
                $candTs = $cand->getDateDebut()?->getTimestamp() ?? 0;
                $diff = abs($scanTs - $candTs);
                if ($diff < $minDiff) {
                    $minDiff = $diff;
                    $bestCandidate = $cand;
                }
            }

            return $bestCandidate;
        };

        // Helper function to find matching presence for an event
        $findPresenceForEvent = function (\App\Entity\Evenement $e, array $presenceList): ?Presence {
            $candidates = [];
            foreach ($presenceList as $p) {
                if ($p->getActivityName() === $e->getNom()) {
                    $candidates[] = $p;
                }
            }

            if (empty($candidates)) {
                return null;
            }

            if (count($candidates) === 1) {
                return $candidates[0];
            }

            $evtTs = $e->getDateDebut()?->getTimestamp() ?? 0;
            $bestPresence = null;
            $minDiff = PHP_INT_MAX;

            foreach ($candidates as $cand) {
                $scanTs = $cand->getScannedAt()?->getTimestamp() ?? 0;
                $diff = abs($scanTs - $evtTs);
                if ($diff < $minDiff) {
                    $minDiff = $diff;
                    $bestPresence = $cand;
                }
            }

            return $bestPresence;
        };

        // Enriched pointage logs for Presence History table
        $presenceLogs = [];
        foreach ($presences as $p) {
            $isLate = false;
            $delayMinutes = 0;

            $matchedEvent = $findBestMatchingEvent($p, $allEvents);

            if ($matchedEvent && $matchedEvent->getDateDebut() && $p->getScannedAt()) {
                $startTs = $matchedEvent->getDateDebut()->getTimestamp();
                $scanTs = $p->getScannedAt()->getTimestamp();
                if ($scanTs > $startTs) {
                    $isLate = true;
                    $delayMinutes = (int) ceil(($scanTs - $startTs) / 60);
                }
            }

            $presenceLogs[] = [
                'presence' => $p,
                'event' => $matchedEvent,
                'isLate' => $isLate,
                'delayMinutes' => $delayMinutes,
                'status' => $isLate ? 'late' : 'present',
            ];
        }

        // Process relevant events for presence & absence rates (Associations & Groups)
        $relevantEventsDetails = [];
        $totalEventsCount = 0;
        $attendedCount = 0;
        $lateCount = 0;
        $onTimeCount = 0;
        $absentCount = 0;

        // Breakdown stats by Association, Groupe, and Fiangonana
        $assocStats = [];
        foreach ($membre->getAssociations() as $a) {
            $assocStats[$a->getId()] = [
                'association' => $a,
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
            $groupeStat = [
                'groupe' => $membre->getZoneGeographique(),
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
            $fiangonanaStat = [
                'fiangonana' => $membre->getFiangonana(),
                'totalEvents' => 0,
                'attended' => 0,
                'late' => 0,
                'absent' => 0,
                'presenceRate' => 0.0,
                'absenceRate' => 0.0,
            ];
        }

        foreach ($allEvents as $e) {
            // Exclude future events from absence statistics
            if ($e->getDateDebut() && $e->getDateDebut() > $now) {
                continue;
            }

            $isRelevant = false;
            $contextType = 'global';
            $contextLabel = 'Événement Général';
            $assocObj = null;

            // Strict filtering by entity belonging:
            // 1. If event belongs to an association, it is ONLY relevant if member belongs to that association.
            // 2. Otherwise if event belongs to a groupe, it is ONLY relevant if member belongs to that groupe.
            // 3. Otherwise if event belongs to a fiangonana, it is ONLY relevant if member belongs to that fiangonana.
            // 4. Otherwise if event has no association, groupe, or fiangonana, it is a general event for everyone.
            if ($e->getAssociation()) {
                if (in_array($e->getAssociation()->getId(), $memberAssocIds, true)) {
                    $isRelevant = true;
                    $contextType = 'association';
                    $assocObj = $e->getAssociation();
                    $contextLabel = 'Assoc: ' . $assocObj->getNom();
                }
            } elseif ($e->getGroupe()) {
                if ($memberGroupeId && $e->getGroupe()->getId() === $memberGroupeId) {
                    $isRelevant = true;
                    $contextType = 'groupe';
                    $contextLabel = 'Zone/Groupe: ' . $e->getGroupe()->getNom();
                }
            } elseif ($e->getFiangonana()) {
                if ($memberFiangonanaId && $e->getFiangonana()->getId() === $memberFiangonanaId) {
                    $isRelevant = true;
                    $contextType = 'fiangonana';
                    $contextLabel = 'Paroisse: ' . $e->getFiangonana()->getNom();
                }
            } else {
                $isRelevant = true;
                $contextType = 'global';
                $contextLabel = 'Général';
            }

            if (!$isRelevant) {
                continue;
            }

            $totalEventsCount++;
            $p = $findPresenceForEvent($e, $presences);
            $isLate = false;
            $delayMinutes = 0;
            $status = 'absent';

            if ($p) {
                $status = 'present';
                $attendedCount++;
                if ($e->getDateDebut() && $p->getScannedAt()) {
                    $startTs = $e->getDateDebut()->getTimestamp();
                    $scanTs = $p->getScannedAt()->getTimestamp();
                    if ($scanTs > $startTs) {
                        $isLate = true;
                        $delayMinutes = (int) ceil(($scanTs - $startTs) / 60);
                        $status = 'late';
                        $lateCount++;
                    } else {
                        $onTimeCount++;
                    }
                } else {
                    $onTimeCount++;
                }
            } else {
                $absentCount++;
            }

            // Update breakdown stats
            if ($contextType === 'association' && $assocObj && isset($assocStats[$assocObj->getId()])) {
                $assocStats[$assocObj->getId()]['totalEvents']++;
                if ($p) {
                    $assocStats[$assocObj->getId()]['attended']++;
                    if ($isLate) {
                        $assocStats[$assocObj->getId()]['late']++;
                    }
                } else {
                    $assocStats[$assocObj->getId()]['absent']++;
                }
            } elseif ($contextType === 'groupe' && $groupeStat) {
                $groupeStat['totalEvents']++;
                if ($p) {
                    $groupeStat['attended']++;
                    if ($isLate) {
                        $groupeStat['late']++;
                    }
                } else {
                    $groupeStat['absent']++;
                }
            } elseif ($contextType === 'fiangonana' && $fiangonanaStat) {
                $fiangonanaStat['totalEvents']++;
                if ($p) {
                    $fiangonanaStat['attended']++;
                    if ($isLate) {
                        $fiangonanaStat['late']++;
                    }
                } else {
                    $fiangonanaStat['absent']++;
                }
            }

            $relevantEventsDetails[] = [
                'event' => $e,
                'contextType' => $contextType,
                'contextLabel' => $contextLabel,
                'status' => $status,
                'isLate' => $isLate,
                'delayMinutes' => $delayMinutes,
                'scannedAt' => $p?->getScannedAt(),
            ];
        }

        // Compute percentage rates
        $tauxPresence = $totalEventsCount > 0 ? round(($attendedCount / $totalEventsCount) * 100, 1) : 0.0;
        $tauxAbsence = $totalEventsCount > 0 ? round(($absentCount / $totalEventsCount) * 100, 1) : 0.0;

        foreach ($assocStats as $aId => &$aStat) {
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

        // Fetch cotisations and dons
        $year = (int)date('Y');
        $cotisations = $em->getRepository(\App\Entity\Cotisation::class)->findBy(['membre' => $membre, 'annee' => $year], ['paidAt' => 'DESC']);
        $dons = $em->getRepository(\App\Entity\Don::class)->findBy(['membre' => $membre], ['paidAt' => 'DESC']);

        // Build entity contexts list
        $cotisationContexts = [];

        if ($membre->getFiangonana()) {
            $f = $membre->getFiangonana();
            $cotisationContexts['fiangonana_' . $f->getId()] = [
                'type' => 'fiangonana',
                'id' => $f->getId(),
                'label' => 'Paroisse: ' . $f->getNom(),
                'shortLabel' => $f->getNom(),
            ];
        }

        if ($membre->getZoneGeographique()) {
            $g = $membre->getZoneGeographique();
            $cotisationContexts['groupe_' . $g->getId()] = [
                'type' => 'groupe',
                'id' => $g->getId(),
                'label' => 'Groupe / Zone: ' . $g->getNom(),
                'shortLabel' => $g->getNom(),
            ];
        }

        foreach ($membre->getAssociations() as $a) {
            $cotisationContexts['association_' . $a->getId()] = [
                'type' => 'association',
                'id' => $a->getId(),
                'label' => 'Association: ' . $a->getNom(),
                'shortLabel' => $a->getNom(),
            ];
        }

        if (empty($cotisationContexts)) {
            $cotisationContexts['general'] = [
                'type' => 'general',
                'id' => 0,
                'label' => 'Cotisations Générales',
                'shortLabel' => 'Général',
            ];
        }

        $cotisationMatrices = [];
        foreach ($cotisationContexts as $ctxKey => $ctxData) {
            $matrix = [];
            for ($m = 1; $m <= 12; $m++) {
                $matrix[$m] = [
                    'mois' => $m,
                    'tranches' => [1 => null, 2 => null, 3 => null, 4 => null],
                    'totalPaid' => 0.0,
                ];
            }
            $cotisationMatrices[$ctxKey] = [
                'context' => $ctxData,
                'monthsMatrix' => $matrix,
                'totalCotisationsYear' => 0.0,
                'monthsPaidCount' => 0,
            ];
        }

        foreach ($cotisations as $c) {
            $m = $c->getMois();
            $t = $c->getTranche();
            $val = (float) $c->getMontant();

            if ($m < 1 || $m > 12 || $t < 1 || $t > 4) {
                continue;
            }

            $matchedKey = null;
            if ($c->getAssociation()) {
                $key = 'association_' . $c->getAssociation()->getId();
                if (isset($cotisationMatrices[$key])) {
                    $matchedKey = $key;
                }
            } elseif ($c->getGroupe()) {
                $key = 'groupe_' . $c->getGroupe()->getId();
                if (isset($cotisationMatrices[$key])) {
                    $matchedKey = $key;
                }
            } elseif ($c->getFiangonana()) {
                $key = 'fiangonana_' . $c->getFiangonana()->getId();
                if (isset($cotisationMatrices[$key])) {
                    $matchedKey = $key;
                }
            }

            if (!$matchedKey) {
                $matchedKey = array_key_first($cotisationMatrices);
            }

            if ($matchedKey && isset($cotisationMatrices[$matchedKey])) {
                $cotisationMatrices[$matchedKey]['monthsMatrix'][$m]['tranches'][$t] = $c;
                $cotisationMatrices[$matchedKey]['monthsMatrix'][$m]['totalPaid'] += $val;
                $cotisationMatrices[$matchedKey]['totalCotisationsYear'] += $val;
            }
        }

        foreach ($cotisationMatrices as $ctxKey => &$ctxData) {
            $paidMonths = 0;
            foreach ($ctxData['monthsMatrix'] as $m => $mInfo) {
                if ($mInfo['totalPaid'] > 0) {
                    $paidMonths++;
                }
            }
            $ctxData['monthsPaidCount'] = $paidMonths;
        }
        unset($ctxData);

        $firstCtx = reset($cotisationMatrices);
        $monthsMatrix = $firstCtx['monthsMatrix'];
        $monthsPaidCount = $firstCtx['monthsPaidCount'];
        $totalCotisationsYear = $firstCtx['totalCotisationsYear'];

        $totalDons = 0.0;
        foreach ($dons as $d) {
            $totalDons += (float)$d->getMontant();
        }

        return $this->render('admin/membres/form.html.twig', [
            'current_route' => 'admin_membre',
            'isEdit' => true,
            'membre' => $membre,
            'groupes' => $groupes,
            'fiangonanas' => $fiangonanas,
            'associations' => $associations,
            'roles' => $roles,
            'presences' => $presences,
            'presenceLogs' => $presenceLogs,
            'relevantEventsDetails' => $relevantEventsDetails,
            'totalEventsCount' => $totalEventsCount,
            'attendedCount' => $attendedCount,
            'onTimeCount' => $onTimeCount,
            'lateCount' => $lateCount,
            'absentCount' => $absentCount,
            'tauxPresence' => $tauxPresence,
            'tauxAbsence' => $tauxAbsence,
            'assocStats' => $assocStats,
            'groupeStat' => $groupeStat,
            'fiangonanaStat' => $fiangonanaStat,
            'cotisations' => $cotisations,
            'cotisationMatrices' => $cotisationMatrices,
            'dons' => $dons,
            'monthsMatrix' => $monthsMatrix,
            'monthsPaidCount' => $monthsPaidCount,
            'totalCotisationsYear' => $totalCotisationsYear,
            'totalDons' => $totalDons,
            'year' => $year,
        ]);
    }

    #[Route('/admin/membres/{id}/generate-qrcode', name: 'admin_membre_generate_qrcode', methods: ['POST'])]
    public function generateQrCode(int $id, EntityManagerInterface $em): Response
    {
        $membre = $em->getRepository(Membre::class)->find($id);
        if (!$membre) {
            throw new NotFoundHttpException('Membre introuvable.');
        }

        $token = bin2hex(random_bytes(16));
        $membre->setQrCodeToken($token);
        $em->flush();

        $this->addFlash('success', sprintf('Code QR unique généré avec succès pour %s %s !', $membre->getPrenom(), $membre->getNom()));

        return $this->redirectToRoute('admin_membre_edit', ['id' => $membre->getId()]);
    }

    #[Route('/admin/membres/{id}/supprimer', name: 'admin_membre_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $membre = $em->getRepository(Membre::class)->find($id);
        if (!$membre) {
            throw new NotFoundHttpException('Membre introuvable.');
        }

        $nom = $membre->getNom();
        $prenom = $membre->getPrenom();

        // Safely update dependent entities where this member is referenced as scannedBy or enregistrePar
        $em->createQuery('UPDATE App\Entity\Presence p SET p.scannedBy = NULL WHERE p.scannedBy = :m')
            ->setParameter('m', $membre)
            ->execute();

        $em->createQuery('UPDATE App\Entity\Cotisation c SET c.enregistrePar = NULL WHERE c.enregistrePar = :m')
            ->setParameter('m', $membre)
            ->execute();

        $em->createQuery('UPDATE App\Entity\Don d SET d.enregistrePar = NULL WHERE d.enregistrePar = :m')
            ->setParameter('m', $membre)
            ->execute();

        $em->remove($membre);
        $em->flush();

        $this->addFlash('success', sprintf('Membre %s %s supprimé avec succès !', $prenom, $nom));

        return $this->redirectToRoute('admin_membre_index');
    }

    #[Route('/admin/role-assignments/{id}/delete', name: 'admin_role_assignment_delete', methods: ['POST'])]
    public function deleteRoleAssignment(int $id, EntityManagerInterface $em): Response
    {
        $assignment = $em->getRepository(RoleAssignment::class)->find($id);
        if (!$assignment) {
            throw new NotFoundHttpException('Attribution introuvable.');
        }

        $membreId = $assignment->getMembre()?->getId();
        $em->remove($assignment);
        $em->flush();

        $this->addFlash('success', 'Rôle retiré avec succès.');

        return $this->redirectToRoute('admin_membre_edit', ['id' => $membreId]);
    }

    #[Route('/admin/membres/{id}/change-password', name: 'admin_membre_change_password', methods: ['POST'])]
    public function changePassword(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $membre = $em->getRepository(Membre::class)->find($id);
        if (!$membre) {
            throw new NotFoundHttpException('Membre introuvable.');
        }

        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('change_password_' . $membre->getId(), $submittedToken)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_membre_edit', ['id' => $membre->getId()]);
        }

        $newPassword = $request->request->get('new_password', '');
        $confirmPassword = $request->request->get('confirm_password', '');

        if (trim($newPassword) === '') {
            $this->addFlash('error', 'Le nouveau mot de passe ne peut pas être vide.');
            return $this->redirectToRoute('admin_membre_edit', ['id' => $membre->getId()]);
        }

        if (strlen($newPassword) < 6) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
            return $this->redirectToRoute('admin_membre_edit', ['id' => $membre->getId()]);
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('admin_membre_edit', ['id' => $membre->getId()]);
        }

        $hashedPassword = $passwordHasher->hashPassword($membre, $newPassword);
        $membre->setPassword($hashedPassword);
        $em->flush();

        $this->addFlash('success', sprintf('Mot de passe de %s %s modifié avec succès !', $membre->getPrenom(), $membre->getNom()));

        return $this->redirectToRoute('admin_membre_edit', ['id' => $membre->getId()]);
    }
}
