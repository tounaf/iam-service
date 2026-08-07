<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'permission')]
#[ApiResource(
    normalizationContext: ['groups' => ['permission:read']],
    denormalizationContext: ['groups' => ['permission:write']]
)]
class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['permission:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'permissions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['permission:read', 'permission:write'])]
    private ?Role $role = null;

    #[ORM\ManyToOne(targetEntity: Feature::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['permission:read', 'permission:write'])]
    private ?Feature $feature = null;

    #[ORM\Column(length: 50)]
    #[Groups(['permission:read', 'permission:write'])]
    private ?string $action = null; // e.g. READ, WRITE, ADMIN

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFeature(): ?Feature
    {
        return $this->feature;
    }

    public function setFeature(?Feature $feature): self
    {
        $this->feature = $feature;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = strtoupper($action);
        return $this;
    }
}
