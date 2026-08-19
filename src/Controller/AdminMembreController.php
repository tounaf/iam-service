<?php

namespace App\Controller;

use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Membre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AdminMembreController extends AbstractController
{
    #[Route('/admin/membres', name: 'admin_membre_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $membres = $em->getRepository(Membre::class)->findBy([], ['id' => 'DESC']);

        return $this->render('admin/membres/index.html.twig', [
            'current_route' => 'admin_membre',
            'membres' => $membres,
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
            $groupeId = $request->request->get('groupe_id');
            $fiangonanaId = $request->request->get('fiangonana_id');

            $membre = new Membre();
            $membre->setNom($nom);
            $membre->setPrenom($prenom);
            $membre->setEmail($email ?: null);
            $membre->setTelephone($telephone ?: null);
            $membre->setQrCodeToken(bin2hex(random_bytes(16)));

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

            $em->persist($membre);
            $em->flush();

            $this->addFlash('success', sprintf('Membre %s %s inscrit avec succès !', $prenom, $nom));

            return $this->redirectToRoute('admin_membre_index');
        }

        $groupes = $em->getRepository(Groupe::class)->findAll();
        $fiangonanas = $em->getRepository(Fiangonana::class)->findAll();

        return $this->render('admin/membres/form.html.twig', [
            'current_route' => 'admin_membre',
            'isEdit' => false,
            'membre' => null,
            'groupes' => $groupes,
            'fiangonanas' => $fiangonanas,
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
            $nom = trim($request->request->get('nom', ''));
            $prenom = trim($request->request->get('prenom', ''));
            $email = trim($request->request->get('email', ''));
            $telephone = trim($request->request->get('telephone', ''));
            $groupeId = $request->request->get('groupe_id');
            $fiangonanaId = $request->request->get('fiangonana_id');

            $membre->setNom($nom);
            $membre->setPrenom($prenom);
            $membre->setEmail($email ?: null);
            $membre->setTelephone($telephone ?: null);

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

            return $this->redirectToRoute('admin_membre_index');
        }

        $groupes = $em->getRepository(Groupe::class)->findAll();
        $fiangonanas = $em->getRepository(Fiangonana::class)->findAll();

        return $this->render('admin/membres/form.html.twig', [
            'current_route' => 'admin_membre',
            'isEdit' => true,
            'membre' => $membre,
            'groupes' => $groupes,
            'fiangonanas' => $fiangonanas,
        ]);
    }
}
