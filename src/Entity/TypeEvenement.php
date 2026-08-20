<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'type_evenement')]
#[ApiResource(
    normalizationContext: ['groups' => ['type_evenement:read']],
    denormalizationContext: ['groups' => ['type_evenement:write']]
)]
class TypeEvenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['type_evenement:read', 'evenement:read', 'fiangonana:read', 'groupe:read', 'association:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['type_evenement:read', 'type_evenement:write', 'evenement:read', 'fiangonana:read', 'groupe:read', 'association:read'])]
    private ?string $nom = null;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    #[Groups(['type_evenement:read', 'type_evenement:write', 'evenement:read'])]
    private ?string $code = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['type_evenement:read', 'type_evenement:write'])]
    private ?string $description = null;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;
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
}
