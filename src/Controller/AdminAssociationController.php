<?php

namespace App\Controller;

use App\Entity\Association;
use App\Entity\Evenement;
use App\Entity\Fiangonana;
use App\Entity\Presence;
use App\Entity\RoleAssignment;
use App\Entity\TypeEvenement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AdminAssociationController extends AbstractController
{
    #[Route('/admin/associations', name: 'admin_association_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $associations = $em->getRepository(Association::class)->findBy([], ['id' => 'DESC']);

        return $this->render('admin/associations/index.html.twig', [
            'current_route' => 'admin_association',
            'associations' => $associations,
        ]);
    }

    #[Route('/admin/associations/nouveau', name: 'admin_association_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom', ''));
            $description = trim($request->request->get('description', ''));
            $fiangonanaId = $request->request->get('fiangonana_id');

            if ($nom === '' || !$fiangonanaId) {
                $this->addFlash('error', 'Le nom de l\'association et la paroisse sont obligatoires.');
            } else {
                $fiangonana = $em->getRepository(Fiangonana::class)->find($fiangonanaId);
                if (!$fiangonana) {
                    $this->addFlash('error', 'Paroisse invalide.');
                } else {
                    $association = new Association();
                    $association->setNom($nom);
                    $association->setDescription($description ?: null);
                    $association->setFiangonana($fiangonana);

                    $em->persist($association);
                    $em->flush();

                    $this->addFlash('success', sprintf('Association "%s" créée avec succès !', $nom));

                    return $this->redirectToRoute('admin_association_index');
                }
            }
        }

        $fiangonanas = $em->getRepository(Fiangonana::class)->findAll();
        $typesEvenement = $em->getRepository(TypeEvenement::class)->findAll();

        return $this->render('admin/associations/form.html.twig', [
            'current_route' => 'admin_association',
            'isEdit' => false,
            'association' => null,
            'fiangonanas' => $fiangonanas,
            'membres' => [],
            'roleAssignments' => [],
            'events' => [],
            'evenements' => [],
            'typesEvenement' => $typesEvenement,
        ]);
    }

    #[Route('/admin/associations/{id}/editer', name: 'admin_association_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $association = $em->getRepository(Association::class)->find($id);
        if (!$association) {
            throw new NotFoundHttpException('Association introuvable.');
        }

        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom', ''));
            $description = trim($request->request->get('description', ''));
            $fiangonanaId = $request->request->get('fiangonana_id');

            if ($nom === '' || !$fiangonanaId) {
                $this->addFlash('error', 'Le nom de l\'association et la paroisse sont obligatoires.');
            } else {
                $fiangonana = $em->getRepository(Fiangonana::class)->find($fiangonanaId);
                if ($fiangonana) {
                    $association->setNom($nom);
                    $association->setDescription($description ?: null);
                    $association->setFiangonana($fiangonana);

                    $em->flush();

                    $this->addFlash('success', sprintf('Association "%s" mise à jour avec succès !', $nom));

                    return $this->redirectToRoute('admin_association_edit', ['id' => $association->getId()]);
                }
            }
        }

        $fiangonanas = $em->getRepository(Fiangonana::class)->findAll();
        $groupes = $em->getRepository(\App\Entity\Groupe::class)->findAll();
        $associations = $em->getRepository(Association::class)->findAll();

        // Filters for membres tab
        $search = trim((string) $request->query->get('search', ''));
        $fiangonanaFilter = $request->query->get('fiangonana') ? (int) $request->query->get('fiangonana') : null;
        $groupeFilter = $request->query->get('groupe') ? (int) $request->query->get('groupe') : null;
        $assocFilter = $request->query->get('association') !== null && $request->query->get('association') !== ''
            ? (int) $request->query->get('association')
            : $association->getId();
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 50;

        /** @var \App\Repository\MembreRepository $membreRepo */
        $membreRepo = $em->getRepository(\App\Entity\Membre::class);
        $paginator = $membreRepo->findBySearchAndPaginate(
            $search !== '' ? $search : null,
            $fiangonanaFilter,
            $groupeFilter,
            $assocFilter,
            $page,
            $limit
        );

        $totalMembresItems = count($paginator);
        $totalMembresPages = max(1, (int) ceil($totalMembresItems / $limit));

        // Total members registered in this association
        $totalMembersInAssociation = count($association->getMembres());

        // Fetch committee/bureau roles in association context
        $roleAssignments = $em->getRepository(RoleAssignment::class)->findBy(['associationContext' => $association, 'isActive' => true]);

        // Fetch explicit created events for this association
        $evenements = $em->getRepository(Evenement::class)->findBy(['association' => $association], ['createdAt' => 'DESC']);
        $typesEvenement = $em->getRepository(TypeEvenement::class)->findAll();

        // Fetch events and presences for members belonging to this association
        $memberIds = array_map(fn($m) => $m->getId(), $association->getMembres()->toArray());
        $presences = [];
        if (!empty($memberIds)) {
            $presences = $em->getRepository(Presence::class)->createQueryBuilder('p')
                ->where('p.membre IN (:memberIds)')
                ->setParameter('memberIds', $memberIds)
                ->orderBy('p.scannedAt', 'DESC')
                ->getQuery()
                ->getResult();
        }

        // Group presences by event/activity name
        $events = [];
        foreach ($presences as $p) {
            $act = $p->getActivityName();
            if (!isset($events[$act])) {
                $events[$act] = [
                    'name' => $act,
                    'presences' => []
                ];
            }
            $events[$act]['presences'][] = $p;
        }

        $activeTab = $request->query->get('tab', 'general');
        if ($search !== '' || $request->query->has('fiangonana') || $request->query->has('groupe') || $request->query->has('page') || ($request->query->has('association') && (int)$request->query->get('association') !== $association->getId())) {
            $activeTab = 'membres';
        }

        return $this->render('admin/associations/form.html.twig', [
            'current_route' => 'admin_association',
            'isEdit' => true,
            'association' => $association,
            'fiangonanas' => $fiangonanas,
            'groupes' => $groupes,
            'associations' => $associations,
            'membres' => $paginator,
            'totalMembersInAssociation' => $totalMembersInAssociation,
            'totalMembresItems' => $totalMembresItems,
            'membresPage' => $page,
            'totalMembresPages' => $totalMembresPages,
            'limit' => $limit,
            'filters' => [
                'search' => $search,
                'fiangonana' => $fiangonanaFilter,
                'groupe' => $groupeFilter,
                'association' => $assocFilter,
            ],
            'activeTab' => $activeTab,
            'roleAssignments' => $roleAssignments,
            'evenements' => $evenements,
            'typesEvenement' => $typesEvenement,
            'events' => array_values($events),
        ]);
    }

    #[Route('/admin/associations/{id}/nouvel-evenement', name: 'admin_association_add_evenement', methods: ['POST'])]
    public function addEvenement(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $association = $em->getRepository(Association::class)->find($id);
        if (!$association) {
            throw new NotFoundHttpException('Association introuvable.');
        }

        $nom = trim($request->request->get('nom', ''));
        $typeEvenementId = $request->request->get('type_evenement_id');
        $dateDebutStr = $request->request->get('date_debut');
        $dateFinStr = $request->request->get('date_fin');
        $description = trim($request->request->get('description', ''));
        $lieu = trim($request->request->get('lieu', ''));

        if ($nom === '') {
            $this->addFlash('error', 'Le nom de l\'événement est obligatoire.');
        } else {
            $evenement = new Evenement();
            $evenement->setNom($nom);
            $evenement->setDescription($description ?: null);
            $evenement->setLieu($lieu ?: null);
            $evenement->setAssociation($association);
            $evenement->setFiangonana($association->getFiangonana());

            if ($dateDebutStr) {
                try {
                    $evenement->setDateDebut(new \DateTime($dateDebutStr));
                } catch (\Exception $e) {
                }
            }

            if ($dateFinStr) {
                try {
                    $evenement->setDateFin(new \DateTime($dateFinStr));
                } catch (\Exception $e) {
                }
            }

            if ($typeEvenementId) {
                $type = $em->getRepository(TypeEvenement::class)->find($typeEvenementId);
                if ($type) {
                    $evenement->setTypeEvenement($type);
                }
            }

            $em->persist($evenement);
            $em->flush();

            $this->addFlash('success', sprintf('Événement "%s" créé pour l\'association avec succès !', $nom));
        }

        return $this->redirectToRoute('admin_association_edit', ['id' => $association->getId()]);
    }

    #[Route('/admin/associations/{id}/supprimer', name: 'admin_association_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $association = $em->getRepository(Association::class)->find($id);
        if (!$association) {
            throw new NotFoundHttpException('Association introuvable.');
        }

        $nom = $association->getNom();
        $em->remove($association);
        $em->flush();

        $this->addFlash('success', sprintf('Association "%s" supprimée avec succès !', $nom));

        return $this->redirectToRoute('admin_association_index');
    }
}
