<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'cotisation')]
#[ApiResource(
    normalizationContext: ['groups' => ['cotisation:read']],
    denormalizationContext: ['groups' => ['cotisation:write']]
)]
class Cotisation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cotisation:read', 'membre:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Membre::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['cotisation:read', 'cotisation:write'])]
    private ?Membre $membre = null;

    #[ORM\ManyToOne(targetEntity: Fiangonana::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['cotisation:read', 'cotisation:write'])]
    private ?Fiangonana $fiangonana = null;

    #[ORM\ManyToOne(targetEntity: Association::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['cotisation:read', 'cotisation:write'])]
    private ?Association $association = null;

    #[ORM\ManyToOne(targetEntity: Groupe::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['cotisation:read', 'cotisation:write'])]
    private ?Groupe $groupe = null;

    #[ORM\Column]
    #[Groups(['cotisation:read', 'cotisation:write', 'membre:read'])]
    private ?int $annee = null;

    #[ORM\Column]
    #[Groups(['cotisation:read', 'cotisation:write', 'membre:read'])]
    private ?int $mois = null; // 1..12

    #[ORM\Column]
    #[Groups(['cotisation:read', 'cotisation:write', 'membre:read'])]
    private ?int $tranche = 1; // 1..4 (4 fois)

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['cotisation:read', 'cotisation:write', 'membre:read'])]
    private ?string $montant = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['cotisation:read', 'cotisation:write', 'membre:read'])]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\ManyToOne(targetEntity: Membre::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['cotisation:read', 'cotisation:write'])]
    private ?Membre $enregistrePar = null;

    public function __construct()
    {
        $this->paidAt = new \DateTimeImmutable();
        $this->annee = (int)date('Y');
        $this->mois = (int)date('n');
        $this->tranche = 1;
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

    public function getAnnee(): ?int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): self
    {
        $this->annee = $annee;
        return $this;
    }

    public function getMois(): ?int
    {
        return $this->mois;
    }

    public function setMois(int $mois): self
    {
        $this->mois = $mois;
        return $this;
    }

    public function getTranche(): ?int
    {
        return $this->tranche;
    }

    public function setTranche(int $tranche): self
    {
        $this->tranche = $tranche;
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
