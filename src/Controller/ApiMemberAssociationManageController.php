<?php

namespace App\Controller;

use App\Entity\Association;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Membre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiMemberAssociationManageController extends AbstractController
{
    #[Route('/api/association-membres/save', name: 'api_association_membres_save', methods: ['POST'])]
    public function saveMember(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Membre|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $isJson = str_contains($request->headers->get('Content-Type', ''), 'application/json');
        $data = $isJson ? (json_decode($request->getContent(), true) ?: []) : $request->request->all();

        $memberId = isset($data['memberId']) ? (int)$data['memberId'] : null;
        $nom = trim($data['nom'] ?? '');
        $prenom = trim($data['prenom'] ?? '');
        $email = trim($data['email'] ?? '');
        $telephone = trim($data['telephone'] ?? '');
        $adresse = trim($data['adresse'] ?? '');
        $associationId = isset($data['associationId']) ? (int)$data['associationId'] : null;
        $groupeId = isset($data['groupeId']) ? (int)$data['groupeId'] : null;

        if ($nom === '' || $prenom === '' || $email === '') {
            return $this->json(['message' => 'Le nom, le prénom et l\'email sont obligatoires.'], Response::HTTP_BAD_REQUEST);
        }

        // Authorization check: User can edit their own profile, or be an admin, or belong to the association/group being managed
        if ($memberId) {
            $membre = $em->getRepository(Membre::class)->find($memberId);
            if (!$membre) {
                return $this->json(['message' => 'Membre introuvable.'], Response::HTTP_NOT_FOUND);
            }

            $isSelf = $currentUser->getId() === $membre->getId();
            $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles(), true);
            $hasAssociationRight = $associationId && $currentUser->getAssociations()->exists(fn($i, $a) => $a->getId() === $associationId);

            if (!$isSelf && !$isAdmin && !$hasAssociationRight) {
                return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
            }
        } else {
            // Check if email already exists
            $existing = $em->getRepository(Membre::class)->findOneBy(['email' => $email]);
            if ($existing) {
                return $this->json(['message' => 'Un membre existe déjà avec cet email.'], Response::HTTP_CONFLICT);
            }

            $membre = new Membre();
            $membre->setQrCodeToken(bin2hex(random_bytes(16)));

            // Attach default parish
            if ($currentUser->getFiangonana()) {
                $membre->setFiangonana($currentUser->getFiangonana());
            } else {
                $fiangonana = $em->getRepository(Fiangonana::class)->findOneBy([]);
                if ($fiangonana) {
                    $membre->setFiangonana($fiangonana);
                }
            }
        }

        $membre->setNom($nom);
        $membre->setPrenom($prenom);
        $membre->setEmail($email);
        $membre->setTelephone($telephone ?: null);
        $membre->setAdresse($adresse ?: null);

        if ($associationId) {
            $assoc = $em->getRepository(Association::class)->find($associationId);
            if ($assoc) {
                $membre->addAssociation($assoc);
            }
        }

        if ($groupeId) {
            $groupe = $em->getRepository(Groupe::class)->find($groupeId);
            if ($groupe) {
                $membre->setZoneGeographique($groupe);
            }
        }

        // Process file upload if provided
        $file = $request->files->get('photo');
        if ($file instanceof UploadedFile && $file->isValid()) {
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/membres';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }

            $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
            $newFilename = sprintf('assoc_membre_%s.%s', uniqid(), $ext);

            try {
                $file->move($uploadsDir, $newFilename);
                $membre->setPhotoUrl('/uploads/membres/' . $newFilename);
            } catch (FileException $e) {}
        }

        $em->persist($membre);
        $em->flush();

        return $this->json([
            'message' => $memberId ? 'Membre mis à jour avec succès !' : 'Membre ajouté avec succès !',
            'membre' => [
                'id' => $membre->getId(),
                'nom' => $membre->getNom(),
                'prenom' => $membre->getPrenom(),
                'email' => $membre->getEmail(),
                'telephone' => $membre->getTelephone(),
                'adresse' => $membre->getAdresse(),
                'photoUrl' => $membre->getPhotoUrl(),
            ]
        ], $memberId ? Response::HTTP_OK : Response::HTTP_CREATED);
    }
}
