<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Media;
use App\Entity\Membre;
use App\Entity\Note;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiMemberEventEvaluationController extends AbstractController
{
    #[Route('/api/member-events/{id}/add-note', name: 'api_member_event_add_note', methods: ['POST'])]
    public function addNote(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Membre|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $evenement = $em->getRepository(Evenement::class)->find($id);
        if (!$evenement) {
            return $this->json(['message' => 'Événement introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $noteText = trim($data['note'] ?? $request->request->get('note', ''));

        if ($noteText === '') {
            return $this->json(['message' => 'Contenu de la note vide.'], Response::HTTP_BAD_REQUEST);
        }

        $note = new Note();
        $note->setContenu($noteText);
        $em->persist($note);

        $evenement->addNote($note);
        $em->flush();

        return $this->json([
            'message' => 'Note ajoutée avec succès !',
            'notes' => $evenement->getNotesAsArray(),
        ]);
    }

    #[Route('/api/member-events/{id}/compte-rendu', name: 'api_member_event_compte_rendu', methods: ['POST'])]
    public function updateCompteRendu(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Membre|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $evenement = $em->getRepository(Evenement::class)->find($id);
        if (!$evenement) {
            return $this->json(['message' => 'Événement introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $compteRendu = trim($data['compteRendu'] ?? $request->request->get('compteRendu', ''));

        $evenement->setCompteRendu($compteRendu ?: null);
        $em->flush();

        return $this->json([
            'message' => 'Compte-rendu mis à jour avec succès !',
            'compteRendu' => $evenement->getCompteRendu(),
        ]);
    }

    #[Route('/api/member-events/{id}/upload-media', name: 'api_member_event_upload_media', methods: ['POST'])]
    public function uploadMedia(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Membre|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $evenement = $em->getRepository(Evenement::class)->find($id);
        if (!$evenement) {
            return $this->json(['message' => 'Événement introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $addedCount = 0;

        // 1. Text URL input
        $mediaUrlInput = trim($request->request->get('mediaUrl', ''));
        if ($mediaUrlInput !== '') {
            $mediaEntity = new Media();
            $mediaEntity->setUrl($mediaUrlInput);
            $ext = strtolower(pathinfo($mediaUrlInput, PATHINFO_EXTENSION));
            if (in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'avi'], true)) {
                $mediaEntity->setType('video');
            } else {
                $mediaEntity->setType('image');
            }
            $evenement->addMedia($mediaEntity);
            $em->persist($mediaEntity);
            $addedCount++;
        }

        // 2. Uploaded File(s)
        $filesToProcess = [];
        $rawFiles = $request->files->all();

        foreach ($rawFiles as $fileOrArray) {
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

                        $mediaEntity = new Media();
                        $mediaEntity->setUrl('/uploads/events/' . $newFilename);
                        if (in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'avi'], true) || str_contains($file->getClientMimeType(), 'video')) {
                            $mediaEntity->setType('video');
                        } else {
                            $mediaEntity->setType('image');
                        }

                        $evenement->addMedia($mediaEntity);
                        $em->persist($mediaEntity);
                        $addedCount++;
                    } catch (FileException $e) {}
                }
            }
        }

        if ($addedCount > 0) {
            $em->flush();
        }

        return $this->json([
            'message' => sprintf('%d média(s) ajouté(s) avec succès !', $addedCount),
            'mediaUrls' => $evenement->getMediaUrls(),
        ]);
    }
}
