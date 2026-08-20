<?php

namespace App\Controller;

use App\Entity\Fiangonana;
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

        return $this->render('admin/fiangonana/form.html.twig', [
            'current_route' => 'admin_fiangonana',
            'isEdit' => false,
            'fiangonana' => null,
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

                return $this->redirectToRoute('admin_fiangonana_index');
            }
        }

        return $this->render('admin/fiangonana/form.html.twig', [
            'current_route' => 'admin_fiangonana',
            'isEdit' => true,
            'fiangonana' => $fiangonana,
        ]);
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
