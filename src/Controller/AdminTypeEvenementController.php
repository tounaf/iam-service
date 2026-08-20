<?php

namespace App\Controller;

use App\Entity\TypeEvenement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AdminTypeEvenementController extends AbstractController
{
    #[Route('/admin/types-evenement', name: 'admin_type_evenement_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $typesEvenement = $em->getRepository(TypeEvenement::class)->findBy([], ['id' => 'DESC']);

        return $this->render('admin/types_evenement/index.html.twig', [
            'current_route' => 'admin_type_evenement',
            'typesEvenement' => $typesEvenement,
        ]);
    }

    #[Route('/admin/types-evenement/nouveau', name: 'admin_type_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom', ''));
            $code = trim($request->request->get('code', ''));
            $description = trim($request->request->get('description', ''));

            if ($nom === '') {
                $this->addFlash('error', 'Le nom du type d\'événement est obligatoire.');
            } else {
                $typeEvenement = new TypeEvenement();
                $typeEvenement->setNom($nom);
                $typeEvenement->setCode($code ?: null);
                $typeEvenement->setDescription($description ?: null);

                $em->persist($typeEvenement);
                $em->flush();

                $this->addFlash('success', sprintf('Type d\'événement "%s" créé avec succès !', $nom));

                return $this->redirectToRoute('admin_type_evenement_index');
            }
        }

        return $this->render('admin/types_evenement/form.html.twig', [
            'current_route' => 'admin_type_evenement',
            'isEdit' => false,
            'typeEvenement' => null,
        ]);
    }

    #[Route('/admin/types-evenement/{id}/editer', name: 'admin_type_evenement_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $typeEvenement = $em->getRepository(TypeEvenement::class)->find($id);
        if (!$typeEvenement) {
            throw new NotFoundHttpException('Type d\'événement introuvable.');
        }

        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom', ''));
            $code = trim($request->request->get('code', ''));
            $description = trim($request->request->get('description', ''));

            if ($nom === '') {
                $this->addFlash('error', 'Le nom du type d\'événement est obligatoire.');
            } else {
                $typeEvenement->setNom($nom);
                $typeEvenement->setCode($code ?: null);
                $typeEvenement->setDescription($description ?: null);

                $em->flush();

                $this->addFlash('success', sprintf('Type d\'événement "%s" mis à jour avec succès !', $nom));

                return $this->redirectToRoute('admin_type_evenement_index');
            }
        }

        return $this->render('admin/types_evenement/form.html.twig', [
            'current_route' => 'admin_type_evenement',
            'isEdit' => true,
            'typeEvenement' => $typeEvenement,
        ]);
    }

    #[Route('/admin/types-evenement/{id}/supprimer', name: 'admin_type_evenement_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $typeEvenement = $em->getRepository(TypeEvenement::class)->find($id);
        if (!$typeEvenement) {
            throw new NotFoundHttpException('Type d\'événement introuvable.');
        }

        $nom = $typeEvenement->getNom();
        $em->remove($typeEvenement);
        $em->flush();

        $this->addFlash('success', sprintf('Type d\'événement "%s" supprimé avec succès !', $nom));

        return $this->redirectToRoute('admin_type_evenement_index');
    }
}
