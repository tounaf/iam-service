<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\Membre;

class ApiSecurityController extends AbstractController
{
    #[Route('/api/login_check', name: 'api_login_check', methods: ['POST'])]
    public function login(): JsonResponse
    {
        /** @var Membre|null $user */
        $user = $this->getUser();

        if (null === $user) {
            return $this->json([
                'message' => 'Identifiants invalides',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'roles' => $user->getRoles(),
            'qrCodeToken' => $user->getQrCodeToken(),
        ]);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var Membre|null $user */
        $user = $this->getUser();

        if (null === $user) {
            return $this->json(null, Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'roles' => $user->getRoles(),
            'qrCodeToken' => $user->getQrCodeToken(),
        ]);
    }
}
