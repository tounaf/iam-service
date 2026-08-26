<?php

namespace App\Controller;

use App\Entity\Membre;
use App\Service\PermissionResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiSecurityController extends AbstractController
{
    private PermissionResolver $permissionResolver;

    public function __construct(PermissionResolver $permissionResolver)
    {
        $this->permissionResolver = $permissionResolver;
    }

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

        $grantedFeatures = $this->permissionResolver->getGrantedFeatures($user);

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'roles' => $user->getRoles(),
            'qrCodeToken' => $user->getQrCodeToken(),
            'features' => $grantedFeatures,
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

        $grantedFeatures = $this->permissionResolver->getGrantedFeatures($user);

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'roles' => $user->getRoles(),
            'qrCodeToken' => $user->getQrCodeToken(),
            'features' => $grantedFeatures,
        ]);
    }
}
