<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Membre;
use App\Entity\Presence;
use App\Entity\RoleAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AdminGroupeController extends AbstractController
{
    #[Route('/admin/groupes', name: 'admin_groupe_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $groupes = $em->getRepository(Groupe::class)->findBy([], ['id' => 'DESC']);

        return $this->render('admin/groupes/index.html.twig', [
            'current_route' => 'admin_groupe',
            'groupes' => $groupes,
        ]);
    }

    #[Route('/admin/groupes/nouveau', name: 'admin_groupe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom', ''));
            $description = trim($request->request->get('description', ''));
            $fiangonanaId = $request->request->get('fiangonana_id');

            if ($nom === '' || !$fiangonanaId) {
                $this->addFlash('error', 'Le nom du groupe et la paroisse sont obligatoires.');
            } else {
                $fiangonana = $em->getRepository(Fiangonana::class)->find($fiangonanaId);
                if (!$fiangonana) {
                    $this->addFlash('error', 'Paroisse invalide.');
                } else {
                    $groupe = new Groupe();
                    $groupe->setNom($nom);
                    $groupe->setDescription($description ?: null);
                    $groupe->setFiangonana($fiangonana);

                    $em->persist($groupe);
                    $em->flush();

                    $this->addFlash('success', sprintf('Groupe / Zone "%s" créé avec succès !', $nom));

                    return $this->redirectToRoute('admin_groupe_index');
                }
            }
        }

        $fiangonanas = $em->getRepository(Fiangonana::class)->findAll();

        return $this->render('admin/groupes/form.html.twig', [
            'current_route' => 'admin_groupe',
            'isEdit' => false,
            'groupe' => null,
            'fiangonanas' => $fiangonanas,
            'membres' => [],
            'roleAssignments' => [],
            'events' => [],
            'evenements' => [],
        ]);
    }

    #[Route('/admin/groupes/{id}/editer', name: 'admin_groupe_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $groupe = $em->getRepository(Groupe::class)->find($id);
        if (!$groupe) {
            throw new NotFoundHttpException('Groupe introuvable.');
        }

        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom', ''));
            $description = trim($request->request->get('description', ''));
            $fiangonanaId = $request->request->get('fiangonana_id');

            if ($nom === '' || !$fiangonanaId) {
                $this->addFlash('error', 'Le nom du groupe et la paroisse sont obligatoires.');
            } else {
                $fiangonana = $em->getRepository(Fiangonana::class)->find($fiangonanaId);
                if ($fiangonana) {
                    $groupe->setNom($nom);
                    $groupe->setDescription($description ?: null);
                    $groupe->setFiangonana($fiangonana);

                    $em->flush();

                    $this->addFlash('success', sprintf('Groupe / Zone "%s" mis à jour avec succès !', $nom));

                    return $this->redirectToRoute('admin_groupe_edit', ['id' => $groupe->getId()]);
                }
            }
        }

        $fiangonanas = $em->getRepository(Fiangonana::class)->findAll();

        // Fetch members belonging to this group
        $membres = $em->getRepository(Membre::class)->findBy(['zoneGeographique' => $groupe], ['id' => 'DESC']);

        // Fetch committee/bureau roles in group context
        $roleAssignments = $em->getRepository(RoleAssignment::class)->findBy(['groupeContext' => $groupe, 'isActive' => true]);

        // Fetch explicit created events for this group
        $evenements = $em->getRepository(Evenement::class)->findBy(['groupe' => $groupe], ['createdAt' => 'DESC']);

        // Fetch events and presences for members of this group
        $presences = $em->getRepository(Presence::class)->createQueryBuilder('p')
            ->join('p.membre', 'm')
            ->where('m.zoneGeographique = :groupe')
            ->setParameter('groupe', $groupe)
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

        return $this->render('admin/groupes/form.html.twig', [
            'current_route' => 'admin_groupe',
            'isEdit' => true,
            'groupe' => $groupe,
            'fiangonanas' => $fiangonanas,
            'membres' => $membres,
            'roleAssignments' => $roleAssignments,
            'evenements' => $evenements,
            'events' => array_values($events),
        ]);
    }

    #[Route('/admin/groupes/{id}/nouvel-evenement', name: 'admin_groupe_add_evenement', methods: ['POST'])]
    public function addEvenement(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $groupe = $em->getRepository(Groupe::class)->find($id);
        if (!$groupe) {
            throw new NotFoundHttpException('Groupe introuvable.');
        }

        $nom = trim($request->request->get('nom', ''));
        $description = trim($request->request->get('description', ''));
        $lieu = trim($request->request->get('lieu', ''));

        if ($nom === '') {
            $this->addFlash('error', 'Le nom de l\'événement est obligatoire.');
        } else {
            $evenement = new Evenement();
            $evenement->setNom($nom);
            $evenement->setDescription($description ?: null);
            $evenement->setLieu($lieu ?: null);
            $evenement->setGroupe($groupe);
            $evenement->setFiangonana($groupe->getFiangonana());

            $em->persist($evenement);
            $em->flush();

            $this->addFlash('success', sprintf('Événement "%s" créé pour la zone / groupe avec succès !', $nom));
        }

        return $this->redirectToRoute('admin_groupe_edit', ['id' => $groupe->getId()]);
    }

    #[Route('/admin/groupes/{id}/supprimer', name: 'admin_groupe_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $groupe = $em->getRepository(Groupe::class)->find($id);
        if (!$groupe) {
            throw new NotFoundHttpException('Groupe introuvable.');
        }

        $nom = $groupe->getNom();
        $em->remove($groupe);
        $em->flush();

        $this->addFlash('success', sprintf('Groupe / Zone "%s" supprimé avec succès !', $nom));

        return $this->redirectToRoute('admin_groupe_index');
    }
}
