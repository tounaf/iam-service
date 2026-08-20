<?php

namespace App\Controller;

use App\Entity\Association;
use App\Entity\Evenement;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Membre;
use App\Entity\Presence;
use App\Entity\RoleAssignment;
use App\Entity\TypeEvenement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AdminFiangonanaController extends AbstractController
{
    #[Route('/admin/fiangonana', name: 'admin_fiangonana_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $fiangonanas = $em->getRepository(Fiangonana::class)->findBy([], ['id' => 'DESC']);

        return $this->render('admin/fiangonana/index.html.twig', [
            'current_route' => 'admin_fiangonana',
            'fiangonanas' => $fiangonanas,
        ]);
    }

    #[Route('/admin/fiangonana/nouveau', name: 'admin_fiangonana_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom', ''));
            $code = trim($request->request->get('code', ''));

            if ($nom === '' || $code === '') {
                $this->addFlash('error', 'Le nom et le code de la paroisse sont obligatoires.');
            } else {
                $fiangonana = new Fiangonana();
                $fiangonana->setNom($nom);
                $fiangonana->setCode($code);

                $em->persist($fiangonana);
                $em->flush();

                $this->addFlash('success', sprintf('Paroisse "%s" créée avec succès !', $nom));

                return $this->redirectToRoute('admin_fiangonana_index');
            }
        }

        $typesEvenement = $em->getRepository(TypeEvenement::class)->findAll();

        return $this->render('admin/fiangonana/form.html.twig', [
            'current_route' => 'admin_fiangonana',
            'isEdit' => false,
            'fiangonana' => null,
            'membres' => [],
            'groupes' => [],
            'associations' => [],
            'roleAssignments' => [],
            'events' => [],
            'evenements' => [],
            'typesEvenement' => $typesEvenement,
        ]);
    }

    #[Route('/admin/fiangonana/{id}/editer', name: 'admin_fiangonana_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $fiangonana = $em->getRepository(Fiangonana::class)->find($id);
        if (!$fiangonana) {
            throw new NotFoundHttpException('Paroisse introuvable.');
        }

        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom', ''));
            $code = trim($request->request->get('code', ''));

            if ($nom === '' || $code === '') {
                $this->addFlash('error', 'Le nom et le code de la paroisse sont obligatoires.');
            } else {
                $fiangonana->setNom($nom);
                $fiangonana->setCode($code);

                $em->flush();

                $this->addFlash('success', sprintf('Paroisse "%s" mise à jour avec succès !', $nom));

                return $this->redirectToRoute('admin_fiangonana_edit', ['id' => $fiangonana->getId()]);
            }
        }

        // Fetch members of this parish
        $membres = $em->getRepository(Membre::class)->findBy(['fiangonana' => $fiangonana], ['id' => 'DESC']);

        // Fetch groups and associations attached to this parish
        $groupes = $fiangonana->getGroupes();
        $associations = $fiangonana->getAssociations();

        // Fetch committee/bureau roles in parish context
        $roleAssignments = $em->getRepository(RoleAssignment::class)->findBy(['fiangonanaContext' => $fiangonana, 'isActive' => true]);

        // Fetch explicit created events for this parish
        $evenements = $em->getRepository(Evenement::class)->findBy(['fiangonana' => $fiangonana], ['createdAt' => 'DESC']);
        $typesEvenement = $em->getRepository(TypeEvenement::class)->findAll();

        // Fetch events and presences for members of this parish
        $presences = $em->getRepository(Presence::class)->createQueryBuilder('p')
            ->join('p.membre', 'm')
            ->where('m.fiangonana = :fiangonana')
            ->setParameter('fiangonana', $fiangonana)
            ->orderBy('p.scannedAt', 'DESC')
            ->getQuery()
            ->getResult();

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

        return $this->render('admin/fiangonana/form.html.twig', [
            'current_route' => 'admin_fiangonana',
            'isEdit' => true,
            'fiangonana' => $fiangonana,
            'membres' => $membres,
            'groupes' => $groupes,
            'associations' => $associations,
            'roleAssignments' => $roleAssignments,
            'evenements' => $evenements,
            'typesEvenement' => $typesEvenement,
            'events' => array_values($events),
        ]);
    }

    #[Route('/admin/fiangonana/{id}/nouveau-groupe', name: 'admin_fiangonana_add_groupe', methods: ['POST'])]
    public function addGroupe(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $fiangonana = $em->getRepository(Fiangonana::class)->find($id);
        if (!$fiangonana) {
            throw new NotFoundHttpException('Paroisse introuvable.');
        }

        $nom = trim($request->request->get('nom', ''));
        $description = trim($request->request->get('description', ''));

        if ($nom === '') {
            $this->addFlash('error', 'Le nom de la zone / groupe est obligatoire.');
        } else {
            $groupe = new Groupe();
            $groupe->setNom($nom);
            $groupe->setDescription($description ?: null);
            $groupe->setFiangonana($fiangonana);

            $em->persist($groupe);
            $em->flush();

            $this->addFlash('success', sprintf('Zone / Groupe "%s" ajouté à la paroisse avec succès !', $nom));
        }

        return $this->redirectToRoute('admin_fiangonana_edit', ['id' => $fiangonana->getId(), 'tab' => 'groupes']);
    }

    #[Route('/admin/fiangonana/{id}/nouvelle-association', name: 'admin_fiangonana_add_association', methods: ['POST'])]
    public function addAssociation(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $fiangonana = $em->getRepository(Fiangonana::class)->find($id);
        if (!$fiangonana) {
            throw new NotFoundHttpException('Paroisse introuvable.');
        }

        $nom = trim($request->request->get('nom', ''));
        $description = trim($request->request->get('description', ''));

        if ($nom === '') {
            $this->addFlash('error', 'Le nom de l\'association est obligatoire.');
        } else {
            $association = new Association();
            $association->setNom($nom);
            $association->setDescription($description ?: null);
            $association->setFiangonana($fiangonana);

            $em->persist($association);
            $em->flush();

            $this->addFlash('success', sprintf('Association "%s" ajoutée à la paroisse avec succès !', $nom));
        }

        return $this->redirectToRoute('admin_fiangonana_edit', ['id' => $fiangonana->getId(), 'tab' => 'associations']);
    }

    #[Route('/admin/fiangonana/{id}/nouvel-evenement', name: 'admin_fiangonana_add_evenement', methods: ['POST'])]
    public function addEvenement(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $fiangonana = $em->getRepository(Fiangonana::class)->find($id);
        if (!$fiangonana) {
            throw new NotFoundHttpException('Paroisse introuvable.');
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
            $evenement->setFiangonana($fiangonana);

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

            $this->addFlash('success', sprintf('Événement "%s" créé pour la paroisse avec succès !', $nom));
        }

        return $this->redirectToRoute('admin_fiangonana_edit', ['id' => $fiangonana->getId(), 'tab' => 'events']);
    }

    #[Route('/admin/fiangonana/{id}/supprimer', name: 'admin_fiangonana_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $fiangonana = $em->getRepository(Fiangonana::class)->find($id);
        if (!$fiangonana) {
            throw new NotFoundHttpException('Paroisse introuvable.');
        }

        $nom = $fiangonana->getNom();
        $em->remove($fiangonana);
        $em->flush();

        $this->addFlash('success', sprintf('Paroisse "%s" supprimée avec succès !', $nom));

        return $this->redirectToRoute('admin_fiangonana_index');
    }
}
