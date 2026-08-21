<?php

namespace App\Controller;

use App\Entity\Association;
use App\Entity\Evenement;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Membre;
use App\Entity\Presence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AdminEvenementController extends AbstractController
{
    #[Route('/admin/evenements/{id}', name: 'admin_evenement_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        $evenement = $em->getRepository(Evenement::class)->find($id);
        if (!$evenement) {
            throw new NotFoundHttpException('Événement introuvable.');
        }

        // Retrieve presences recorded for this activity name
        $presences = $em->getRepository(Presence::class)->findBy(
            ['activityName' => $evenement->getNom()],
            ['scannedAt' => 'DESC']
        );

        $presentMembers = [];
        foreach ($presences as $p) {
            if ($p->getMembre()) {
                $presentMembers[] = $p;
            }
        }
        $presentCount = count($presentMembers);

        // Calculate total scope members for participation rate
        $totalScopeMembers = 0;
        $scopeName = 'Paroisse';

        if ($evenement->getAssociation()) {
            $scopeName = 'Association ' . $evenement->getAssociation()->getNom();
            $totalScopeMembers = count($evenement->getAssociation()->getMembres());
        } elseif ($evenement->getGroupe()) {
            $scopeName = 'Zone ' . $evenement->getGroupe()->getNom();
            $totalScopeMembers = count($em->getRepository(Membre::class)->findBy(['zoneGeographique' => $evenement->getGroupe()]));
        } elseif ($evenement->getFiangonana()) {
            $scopeName = 'Paroisse ' . $evenement->getFiangonana()->getNom();
            $totalScopeMembers = count($em->getRepository(Membre::class)->findBy(['fiangonana' => $evenement->getFiangonana()]));
        } else {
            $totalScopeMembers = count($em->getRepository(Membre::class)->findAll());
        }

        $tauxParticipation = $totalScopeMembers > 0
            ? round(($presentCount / $totalScopeMembers) * 100, 1)
            : 0;

        return $this->render('admin/evenements/show.html.twig', [
            'current_route' => 'admin_evenement',
            'evenement' => $evenement,
            'presences' => $presentMembers,
            'presentCount' => $presentCount,
            'totalScopeMembers' => $totalScopeMembers,
            'scopeName' => $scopeName,
            'tauxParticipation' => $tauxParticipation,
        ]);
    }

    #[Route('/admin/evenements/{id}/compte-rendu', name: 'admin_evenement_update_compte_rendu', methods: ['POST'])]
    public function updateCompteRendu(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $evenement = $em->getRepository(Evenement::class)->find($id);
        if (!$evenement) {
            throw new NotFoundHttpException('Événement introuvable.');
        }

        $compteRendu = trim($request->request->get('compte_rendu', ''));
        $evenement->setCompteRendu($compteRendu ?: null);

        $em->flush();

        $this->addFlash('success', 'Compte-rendu de l\'événement mis à jour avec succès.');

        return $this->redirectToRoute('admin_evenement_show', ['id' => $evenement->getId()]);
    }

    #[Route('/admin/evenements/{id}/add-note', name: 'admin_evenement_add_note', methods: ['POST'])]
    public function addNote(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $evenement = $em->getRepository(Evenement::class)->find($id);
        if (!$evenement) {
            throw new NotFoundHttpException('Événement introuvable.');
        }

        $newNote = trim($request->request->get('new_note', ''));
        if ($newNote !== '') {
            $evenement->addNote($newNote);
            $em->flush();
            $this->addFlash('success', sprintf('Note "%s" ajoutée à l\'événement.', $newNote));
        }

        return $this->redirectToRoute('admin_evenement_show', ['id' => $evenement->getId()]);
    }

    #[Route('/admin/evenements/{id}/upload-media', name: 'admin_evenement_upload_media', methods: ['POST'])]
    public function uploadMedia(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $evenement = $em->getRepository(Evenement::class)->find($id);
        if (!$evenement) {
            throw new NotFoundHttpException('Événement introuvable.');
        }

        $addedCount = 0;

        // 1. Text URL input
        $mediaUrlInput = trim($request->request->get('media_url', ''));
        if ($mediaUrlInput !== '') {
            $evenement->addMediaUrl($mediaUrlInput);
            $addedCount++;
        }

        // 2. Gather files from all possible form keys
        $filesToProcess = [];
        $rawFiles = $request->files->all();

        foreach ($rawFiles as $key => $fileOrArray) {
            if (is_array($fileOrArray)) {
                foreach ($fileOrArray as $f) {
                    if ($f instanceof UploadedFile) {
                        $filesToProcess[] = $f;
                    }
                }
            } elseif ($fileOrArray instanceof UploadedFile) {
                $filesToProcess[] = $fileOrArray;
            }
        }

        if (!empty($filesToProcess)) {
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/events';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }

            foreach ($filesToProcess as $file) {
                if ($file->isValid()) {
                    $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
                    $newFilename = uniqid('event_media_') . '.' . $ext;
                    try {
                        $file->move($uploadsDir, $newFilename);
                        $evenement->addMediaUrl('/uploads/events/' . $newFilename);
                        $addedCount++;
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Erreur lors du téléversement d\'un média.');
                    }
                }
            }
        }

        if ($addedCount > 0) {
            $em->flush();
            $this->addFlash('success', sprintf('%d média(s) / lien(s) ajouté(s) à l\'événement avec succès.', $addedCount));
        }

        return $this->redirectToRoute('admin_evenement_show', ['id' => $evenement->getId()]);
    }

    #[Route('/admin/evenements/{id}/update', name: 'admin_evenement_update', methods: ['POST'])]
    public function update(int $id, Request $request, EntityManagerInterface $em): Response
    {
        return $this->updateCompteRendu($id, $request, $em);
    }
}
