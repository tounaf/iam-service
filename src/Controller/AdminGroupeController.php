<?php

namespace App\Controller;

use App\Entity\Fiangonana;
use App\Entity\Groupe;
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

                    return $this->redirectToRoute('admin_groupe_index');
                }
            }
        }

        $fiangonanas = $em->getRepository(Fiangonana::class)->findAll();

        return $this->render('admin/groupes/form.html.twig', [
            'current_route' => 'admin_groupe',
            'isEdit' => true,
            'groupe' => $groupe,
            'fiangonanas' => $fiangonanas,
        ]);
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
