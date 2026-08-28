<?php

namespace App\Controller;

use App\Entity\Membre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MembreProfileUpdateController extends AbstractController
{
    #[Route('/api/membres/{id}/update-profile', name: 'api_membre_update_profile', methods: ['POST', 'PATCH'])]
    public function updateProfile(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof Membre) {
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        // Members can update their own profile, or admins can update any member profile
        if ($currentUser->getId() !== $id && !in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return $this->json(['message' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $membre = $em->getRepository(Membre::class)->find($id);
        if (!$membre) {
            return $this->json(['message' => 'Membre introuvable'], Response::HTTP_NOT_FOUND);
        }

        // Parse JSON data or Form Data
        $data = [];
        if ($request->getContentTypeFormat() === 'json' || str_contains($request->headers->get('Content-Type', ''), 'application/json')) {
            $data = json_decode($request->getContent(), true) ?: [];
        } else {
            $data = $request->request->all();
        }

        if (isset($data['nom']) && trim($data['nom']) !== '') {
            $membre->setNom(trim($data['nom']));
        }
        if (isset($data['prenom']) && trim($data['prenom']) !== '') {
            $membre->setPrenom(trim($data['prenom']));
        }
        if (isset($data['email']) && trim($data['email']) !== '') {
            $membre->setEmail(trim($data['email']));
        }
        if (array_key_exists('telephone', $data)) {
            $membre->setTelephone(trim($data['telephone']) ?: null);
        }
        if (array_key_exists('adresse', $data)) {
            $membre->setAdresse(trim($data['adresse']) ?: null);
        }
        if (array_key_exists('dateNaissance', $data)) {
            $val = trim((string)$data['dateNaissance']);
            $membre->setDateNaissance($val ? new \DateTime($val) : null);
        }

        // Handle profile photo upload if present in multipart form
        $file = $request->files->get('photo');
        if ($file instanceof UploadedFile && $file->isValid()) {
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/membres';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }

            $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
            $newFilename = sprintf('profile_%d_%s.%s', $membre->getId(), uniqid(), $ext);

            try {
                $file->move($uploadsDir, $newFilename);
                $membre->setPhotoUrl('/uploads/membres/' . $newFilename);
            } catch (FileException $e) {
                return $this->json(['message' => 'Erreur lors de l\'enregistrement de la photo'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $em->flush();

        return $this->json([
            'message' => 'Profil mis à jour avec succès',
            'membre' => [
                'id' => $membre->getId(),
                'nom' => $membre->getNom(),
                'prenom' => $membre->getPrenom(),
                'email' => $membre->getEmail(),
                'telephone' => $membre->getTelephone(),
                'adresse' => $membre->getAdresse(),
                'dateNaissance' => $membre->getDateNaissance()?->format('Y-m-d'),
                'age' => $membre->getAge(),
                'photoUrl' => $membre->getPhotoUrl(),
            ]
        ]);
    }
}
