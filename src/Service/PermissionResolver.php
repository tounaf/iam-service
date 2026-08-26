<?php

namespace App\Service;

use App\Entity\Feature;
use App\Entity\Membre;
use App\Entity\RoleAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PermissionResolver
{
    private EntityManagerInterface $entityManager;
    private CacheInterface $cache;

    public function __construct(EntityManagerInterface $entityManager, CacheInterface $cache)
    {
        $this->entityManager = $entityManager;
        $this->cache = $cache;
    }

    public function getCacheKey(Membre $membre): string
    {
        return sprintf('user_features_%d', $membre->getId());
    }

    /**
     * Resolves and caches all granted features for a member.
     * Cache persists across requests until logout or explicit invalidation.
     *
     * @return array<string, array<string>> Keyed by feature code -> array of granted actions (e.g. ['READ', 'WRITE'])
     */
    public function getGrantedFeatures(Membre $membre): array
    {
        $cacheKey = $this->getCacheKey($membre);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($membre) {
            // Keep in cache without expiry until explicitly cleared at logout
            $item->expiresAfter(null);

            return $this->calculateFeatures($membre);
        });
    }

    /**
     * Checks if a member has access to a specific feature code (and optional action).
     */
    public function isFeatureGranted(Membre $membre, string $featureCode, ?string $action = null): bool
    {
        // Admin role override
        if (in_array('ROLE_ADMIN', $membre->getRoles(), true)) {
            return true;
        }

        $grantedFeatures = $this->getGrantedFeatures($membre);
        $featureCode = strtoupper($featureCode);

        if (!isset($grantedFeatures[$featureCode])) {
            return false;
        }

        if ($action === null) {
            return true;
        }

        return in_array(strtoupper($action), $grantedFeatures[$featureCode], true);
    }

    /**
     * Clears cached features for a member (called on logout).
     */
    public function invalidateCache(Membre $membre): void
    {
        $this->cache->delete($this->getCacheKey($membre));
    }

    /**
     * Re-calculates features directly from the database without updating cache.
     */
    public function calculateFeatures(Membre $membre): array
    {
        // Admin role gets all features
        if (in_array('ROLE_ADMIN', $membre->getRoles(), true)) {
            $allFeatures = $this->entityManager->getRepository(Feature::class)->findAll();
            $features = [];
            foreach ($allFeatures as $feature) {
                if ($feature->getCode()) {
                    $features[strtoupper($feature->getCode())] = ['READ', 'WRITE', 'ADMIN', 'EXECUTE'];
                }
            }
            return $features;
        }

        $now = new \DateTimeImmutable();
        $roleAssignments = $this->entityManager->getRepository(RoleAssignment::class)->findBy([
            'membre' => $membre,
            'isActive' => true,
        ]);

        $granted = [];

        foreach ($roleAssignments as $assignment) {
            if ($assignment->getStartDate() > $now) {
                continue;
            }
            if ($assignment->getEndDate() !== null && $assignment->getEndDate() < $now) {
                continue;
            }

            $role = $assignment->getRole();
            if ($role === null) {
                continue;
            }

            foreach ($role->getPermissions() as $permission) {
                $feature = $permission->getFeature();
                if ($feature === null || !$feature->getCode()) {
                    continue;
                }

                $code = strtoupper($feature->getCode());
                $action = strtoupper((string) $permission->getAction());

                if (!isset($granted[$code])) {
                    $granted[$code] = [];
                }

                if (!in_array($action, $granted[$code], true)) {
                    $granted[$code][] = $action;
                }
            }
        }

        return $granted;
    }
}
