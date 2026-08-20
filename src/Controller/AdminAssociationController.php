<?php

namespace App\Controller;

use App\Entity\Association;
use App\Entity\Fiangonana;
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

        return $this->render('admin/associations/form.html.twig', [
            'current_route' => 'admin_association',
            'isEdit' => false,
            'association' => null,
            'fiangonanas' => $fiangonanas,
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

                    return $this->redirectToRoute('admin_association_index');
                }
            }
        }

        $fiangonanas = $em->getRepository(Fiangonana::class)->findAll();

        return $this->render('admin/associations/form.html.twig', [
            'current_route' => 'admin_association',
            'isEdit' => true,
            'association' => $association,
            'fiangonanas' => $fiangonanas,
        ]);
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
