<?php

namespace App\Controller;

use App\Entity\Membre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ApiMemberPasswordController extends AbstractController
{
    #[Route('/api/me/change-password', name: 'api_me_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        /** @var Membre|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?: [];

        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';

        if (trim($newPassword) === '') {
            return $this->json(['message' => 'Le nouveau mot de passe ne peut pas être vide.'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($newPassword) < 6) {
            return $this->json(['message' => 'Le nouveau mot de passe doit contenir au moins 6 caractères.'], Response::HTTP_BAD_REQUEST);
        }

        // Verify current password if user already has a password set
        if ($user->getPassword() !== null && $currentPassword !== '') {
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                return $this->json(['message' => 'Le mot de passe actuel est incorrect.'], Response::HTTP_BAD_REQUEST);
            }
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        $em->flush();

        return $this->json([
            'message' => 'Mot de passe modifié avec succès !',
        ]);
    }
}
