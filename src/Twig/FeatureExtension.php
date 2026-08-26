<?php

namespace App\Twig;

use App\Entity\Membre;
use App\Service\PermissionResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FeatureExtension extends AbstractExtension
{
    private Security $security;
    private PermissionResolver $permissionResolver;

    public function __construct(Security $security, PermissionResolver $permissionResolver)
    {
        $this->security = $security;
        $this->permissionResolver = $permissionResolver;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_feature_granted', [$this, 'isFeatureGranted']),
            new TwigFunction('get_granted_features', [$this, 'getGrantedFeatures']),
        ];
    }

    public function isFeatureGranted(string $featureCode, ?string $action = null): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof Membre) {
            return false;
        }

        return $this->permissionResolver->isFeatureGranted($user, $featureCode, $action);
    }

    public function getGrantedFeatures(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof Membre) {
            return [];
        }

        return $this->permissionResolver->getGrantedFeatures($user);
    }
}
