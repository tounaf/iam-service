<?php

namespace App\Controller;

use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AdminRoleController extends AbstractController
{
    #[Route('/admin/roles', name: 'admin_role_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $roles = $em->getRepository(Role::class)->findBy([], ['id' => 'DESC']);

        return $this->render('admin/roles/index.html.twig', [
            'current_route' => 'admin_role',
            'roles' => $roles,
        ]);
    }

    #[Route('/admin/roles/nouveau', name: 'admin_role_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $name = trim($request->request->get('name', ''));
            $description = trim($request->request->get('description', ''));

            if ($name === '') {
                $this->addFlash('error', 'Le nom du rôle est obligatoire.');
            } else {
                $role = new Role();
                $role->setName($name);
                $role->setDescription($description ?: null);

                $em->persist($role);
                $em->flush();

                $this->addFlash('success', sprintf('Rôle "%s" créé avec succès !', $role->getName()));

                return $this->redirectToRoute('admin_role_index');
            }
        }

        return $this->render('admin/roles/form.html.twig', [
            'current_route' => 'admin_role',
            'isEdit' => false,
            'role' => null,
        ]);
    }

    #[Route('/admin/roles/{id}/editer', name: 'admin_role_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $role = $em->getRepository(Role::class)->find($id);
        if (!$role) {
            throw new NotFoundHttpException('Rôle introuvable.');
        }

        if ($request->isMethod('POST')) {
            $name = trim($request->request->get('name', ''));
            $description = trim($request->request->get('description', ''));

            if ($name === '') {
                $this->addFlash('error', 'Le nom du rôle est obligatoire.');
            } else {
                $role->setName($name);
                $role->setDescription($description ?: null);

                $em->flush();

                $this->addFlash('success', sprintf('Rôle "%s" mis à jour avec succès !', $role->getName()));

                return $this->redirectToRoute('admin_role_index');
            }
        }

        return $this->render('admin/roles/form.html.twig', [
            'current_route' => 'admin_role',
            'isEdit' => true,
            'role' => $role,
        ]);
    }

    #[Route('/admin/roles/{id}/supprimer', name: 'admin_role_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $role = $em->getRepository(Role::class)->find($id);
        if (!$role) {
            throw new NotFoundHttpException('Rôle introuvable.');
        }

        $name = $role->getName();
        $em->remove($role);
        $em->flush();

        $this->addFlash('success', sprintf('Rôle "%s" supprimé avec succès !', $name));

        return $this->redirectToRoute('admin_role_index');
    }
}
