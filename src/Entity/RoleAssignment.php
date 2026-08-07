<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'role_assignment')]
#[ApiResource(
    normalizationContext: ['groups' => ['role_assignment:read']],
    denormalizationContext: ['groups' => ['role_assignment:write']]
)]
class RoleAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['role_assignment:read', 'membre:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Membre::class, inversedBy: 'roleAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['role_assignment:read', 'role_assignment:write'])]
    private ?Membre $membre = null;

    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['role_assignment:read', 'role_assignment:write', 'membre:read'])]
    private ?Role $role = null;

    #[ORM\ManyToOne(targetEntity: Fiangonana::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['role_assignment:read', 'role_assignment:write', 'membre:read'])]
    private ?Fiangonana $fiangonanaContext = null;

    #[ORM\ManyToOne(targetEntity: Groupe::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['role_assignment:read', 'role_assignment:write', 'membre:read'])]
    private ?Groupe $groupeContext = null;

    #[ORM\ManyToOne(targetEntity: Association::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['role_assignment:read', 'role_assignment:write', 'membre:read'])]
    private ?Association $associationContext = null;

    #[ORM\ManyToOne(targetEntity: SousGroupe::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['role_assignment:read', 'role_assignment:write', 'membre:read'])]
    private ?SousGroupe $sousGroupeContext = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['role_assignment:read', 'role_assignment:write'])]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['role_assignment:read', 'role_assignment:write'])]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(length: 10)]
    #[Groups(['role_assignment:read', 'role_assignment:write'])]
    private ?string $exerciceYear = null;

    #[ORM\Column]
    #[Groups(['role_assignment:read', 'role_assignment:write', 'membre:read'])]
    private ?bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMembre(): ?Membre
    {
        return $this->membre;
    }

    public function setMembre(?Membre $membre): self
    {
        $this->membre = $membre;
        return $this;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getFiangonanaContext(): ?Fiangonana
    {
        return $this->fiangonanaContext;
    }

    public function setFiangonanaContext(?Fiangonana $fiangonanaContext): self
    {
        $this->fiangonanaContext = $fiangonanaContext;
        return $this;
    }

    public function getGroupeContext(): ?Groupe
    {
        return $this->groupeContext;
    }

    public function setGroupeContext(?Groupe $groupeContext): self
    {
        $this->groupeContext = $groupeContext;
        return $this;
    }

    public function getAssociationContext(): ?Association
    {
        return $this->associationContext;
    }

    public function setAssociationContext(?Association $associationContext): self
    {
        $this->associationContext = $associationContext;
        return $this;
    }

    public function getSousGroupeContext(): ?SousGroupe
    {
        return $this->sousGroupeContext;
    }

    public function setSousGroupeContext(?SousGroupe $sousGroupeContext): self
    {
        $this->sousGroupeContext = $sousGroupeContext;
        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getExerciceYear(): ?string
    {
        return $this->exerciceYear;
    }

    public function setExerciceYear(string $exerciceYear): self
    {
        $this->exerciceYear = $exerciceYear;
        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }
}
