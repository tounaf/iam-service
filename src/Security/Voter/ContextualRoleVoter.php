<?php

namespace App\Security\Voter;

use App\Entity\Association;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Membre;
use App\Entity\Permission;
use App\Entity\RoleAssignment;
use App\Entity\SousGroupe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ContextualRoleVoter extends Voter
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Supports actions like READ, WRITE, ADMIN
        if (!in_array(strtoupper($attribute), ['READ', 'WRITE', 'ADMIN'])) {
            return false;
        }

        // Supports subjects that are part of our context hierarchy
        return $subject instanceof Fiangonana
            || $subject instanceof Groupe
            || $subject instanceof Association
            || $subject instanceof SousGroupe;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Membre) {
            return false;
        }

        // 1. Resolve contexts hierarchically
        $contexts = $this->resolveContexts($subject);

        // 2. Fetch all active role assignments for this member
        $roleAssignments = $this->entityManager->getRepository(RoleAssignment::class)->findBy([
            'membre' => $user,
            'isActive' => true
        ]);

        // 3. Match assignments against resolved contexts and verify permissions
        foreach ($roleAssignments as $assignment) {
            // Check temporal bounds
            $now = new \DateTimeImmutable();
            if ($assignment->getStartDate() > $now) {
                continue;
            }
            if ($assignment->getEndDate() !== null && $assignment->getEndDate() < $now) {
                continue;
            }

            // Check if this assignment matches any of our resolved contexts
            if ($this->matchesContext($assignment, $contexts)) {
                // Resolve the permissions for this assignment's role
                $role = $assignment->getRole();
                if ($role !== null) {
                    foreach ($role->getPermissions() as $permission) {
                        // Check if the permission matches the action and is active
                        if (strtoupper($permission->getAction()) === strtoupper($attribute)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function resolveContexts(mixed $subject): array
    {
        $contexts = [];

        if ($subject instanceof Fiangonana) {
            $contexts['fiangonana'] = $subject;
        } elseif ($subject instanceof Groupe) {
            $contexts['groupe'] = $subject;
            if ($subject->getFiangonana() !== null) {
                $contexts['fiangonana'] = $subject->getFiangonana();
            }
        } elseif ($subject instanceof Association) {
            $contexts['association'] = $subject;
            if ($subject->getFiangonana() !== null) {
                $contexts['fiangonana'] = $subject->getFiangonana();
            }
        } elseif ($subject instanceof SousGroupe) {
            $contexts['sous_groupe'] = $subject;
            if ($subject->getAssociation() !== null) {
                $contexts['association'] = $subject->getAssociation();
                if ($subject->getAssociation()->getFiangonana() !== null) {
                    $contexts['fiangonana'] = $subject->getAssociation()->getFiangonana();
                }
            }
        }

        return $contexts;
    }

    private function matchesContext(RoleAssignment $assignment, array $contexts): bool
    {
        // If assignment is global church level
        if ($assignment->getFiangonanaContext() !== null && isset($contexts['fiangonana'])) {
            if ($assignment->getFiangonanaContext()->getId() === $contexts['fiangonana']->getId()) {
                return true;
            }
        }

        // If assignment is group/zone level
        if ($assignment->getGroupeContext() !== null && isset($contexts['groupe'])) {
            if ($assignment->getGroupeContext()->getId() === $contexts['groupe']->getId()) {
                return true;
            }
        }

        // If assignment is association level
        if ($assignment->getAssociationContext() !== null && isset($contexts['association'])) {
            if ($assignment->getAssociationContext()->getId() === $contexts['association']->getId()) {
                return true;
            }
        }

        // If assignment is sub-group level
        if ($assignment->getSousGroupeContext() !== null && isset($contexts['sous_groupe'])) {
            if ($assignment->getSousGroupeContext()->getId() === $contexts['sous_groupe']->getId()) {
                return true;
            }
        }

        return false;
    }
}
