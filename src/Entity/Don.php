<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'don')]
#[ApiResource(
    normalizationContext: ['groups' => ['don:read']],
    denormalizationContext: ['groups' => ['don:write']]
)]
class Don
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['don:read', 'membre:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Membre::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['don:read', 'don:write'])]
    private ?Membre $membre = null;

    #[ORM\ManyToOne(targetEntity: Fiangonana::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['don:read', 'don:write'])]
    private ?Fiangonana $fiangonana = null;

    #[ORM\ManyToOne(targetEntity: Association::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['don:read', 'don:write'])]
    private ?Association $association = null;

    #[ORM\ManyToOne(targetEntity: Groupe::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['don:read', 'don:write'])]
    private ?Groupe $groupe = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['don:read', 'don:write', 'membre:read'])]
    private ?string $montant = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['don:read', 'don:write', 'membre:read'])]
    private ?string $libelle = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['don:read', 'don:write', 'membre:read'])]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\ManyToOne(targetEntity: Membre::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['don:read', 'don:write'])]
    private ?Membre $enregistrePar = null;

    public function __construct()
    {
        $this->paidAt = new \DateTimeImmutable();
    }

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

    public function getFiangonana(): ?Fiangonana
    {
        return $this->fiangonana;
    }

    public function setFiangonana(?Fiangonana $fiangonana): self
    {
        $this->fiangonana = $fiangonana;
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

    public function getGroupe(): ?Groupe
    {
        return $this->groupe;
    }

    public function setGroupe(?Groupe $groupe): self
    {
        $this->groupe = $groupe;
        return $this;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): self
    {
        $this->montant = $montant;
        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): self
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(\DateTimeImmutable $paidAt): self
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getEnregistrePar(): ?Membre
    {
        return $this->enregistrePar;
    }

    public function setEnregistrePar(?Membre $enregistrePar): self
    {
        $this->enregistrePar = $enregistrePar;
        return $this;
    }
}
