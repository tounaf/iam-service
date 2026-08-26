<?php

namespace App\Controller;

use App\Entity\Feature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AdminFeatureController extends AbstractController
{
    #[Route('/admin/features', name: 'admin_feature_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $features = $em->getRepository(Feature::class)->findBy([], ['sortOrder' => 'ASC', 'id' => 'DESC']);

        return $this->render('admin/features/index.html.twig', [
            'current_route' => 'admin_role',
            'features' => $features,
        ]);
    }

    #[Route('/admin/features/nouveau', name: 'admin_feature_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $code = trim((string) $request->request->get('code', ''));
            $label = trim((string) $request->request->get('label', ''));
            $category = trim((string) $request->request->get('category', ''));
            $description = trim((string) $request->request->get('description', ''));
            $targetRoute = trim((string) $request->request->get('targetRoute', ''));
            $icon = trim((string) $request->request->get('icon', ''));
            $sortOrder = (int) $request->request->get('sortOrder', 0);

            if ($code === '' || $label === '') {
                $this->addFlash('error', 'Le code et le libellé sont obligatoires.');
            } else {
                $feature = new Feature();
                $feature->setCode($code);
                $feature->setLabel($label);
                $feature->setCategory($category ?: null);
                $feature->setDescription($description ?: null);
                $feature->setTargetRoute($targetRoute ?: null);
                $feature->setIcon($icon ?: null);
                $feature->setSortOrder($sortOrder);

                $em->persist($feature);
                $em->flush();

                $this->addFlash('success', sprintf('Fonctionnalité "%s" créée avec succès !', $feature->getCode()));

                return $this->redirectToRoute('admin_feature_index');
            }
        }

        return $this->render('admin/features/form.html.twig', [
            'current_route' => 'admin_role',
            'isEdit' => false,
            'feature' => null,
        ]);
    }

    #[Route('/admin/features/{id}/editer', name: 'admin_feature_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $feature = $em->getRepository(Feature::class)->find($id);
        if (!$feature) {
            throw new NotFoundHttpException('Fonctionnalité introuvable.');
        }

        if ($request->isMethod('POST')) {
            $code = trim((string) $request->request->get('code', ''));
            $label = trim((string) $request->request->get('label', ''));
            $category = trim((string) $request->request->get('category', ''));
            $description = trim((string) $request->request->get('description', ''));
            $targetRoute = trim((string) $request->request->get('targetRoute', ''));
            $icon = trim((string) $request->request->get('icon', ''));
            $sortOrder = (int) $request->request->get('sortOrder', 0);

            if ($code === '' || $label === '') {
                $this->addFlash('error', 'Le code et le libellé sont obligatoires.');
            } else {
                $feature->setCode($code);
                $feature->setLabel($label);
                $feature->setCategory($category ?: null);
                $feature->setDescription($description ?: null);
                $feature->setTargetRoute($targetRoute ?: null);
                $feature->setIcon($icon ?: null);
                $feature->setSortOrder($sortOrder);

                $em->flush();

                $this->addFlash('success', sprintf('Fonctionnalité "%s" mise à jour avec succès !', $feature->getCode()));

                return $this->redirectToRoute('admin_feature_index');
            }
        }

        return $this->render('admin/features/form.html.twig', [
            'current_route' => 'admin_role',
            'isEdit' => true,
            'feature' => $feature,
        ]);
    }

    #[Route('/admin/features/{id}/supprimer', name: 'admin_feature_delete', methods: ['POST'])]
    public function delete(int $id, EntityManagerInterface $em): Response
    {
        $feature = $em->getRepository(Feature::class)->find($id);
        if (!$feature) {
            throw new NotFoundHttpException('Fonctionnalité introuvable.');
        }

        $code = $feature->getCode();
        $em->remove($feature);
        $em->flush();

        $this->addFlash('success', sprintf('Fonctionnalité "%s" supprimée avec succès !', $code));

        return $this->redirectToRoute('admin_feature_index');
    }
}
