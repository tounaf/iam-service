<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'sous_groupe')]
#[ApiResource(
    normalizationContext: ['groups' => ['sous_groupe:read']],
    denormalizationContext: ['groups' => ['sous_groupe:write']]
)]
class SousGroupe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['sous_groupe:read', 'association:read', 'role_assignment:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['sous_groupe:read', 'sous_groupe:write', 'association:read', 'role_assignment:read'])]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['sous_groupe:read', 'sous_groupe:write'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Association::class, inversedBy: 'sousGroupes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['sous_groupe:read', 'sous_groupe:write'])]
    private ?Association $association = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getAssociation(): ?Association
    {
        return $this->association;
    }

    public function setAssociation(?Association $association): self
    {
        $this->association = $association;
        return $this;
    }
}
