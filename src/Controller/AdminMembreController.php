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
}
