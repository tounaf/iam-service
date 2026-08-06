<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'association')]
#[ApiResource(
    normalizationContext: ['groups' => ['association:read']],
    denormalizationContext: ['groups' => ['association:write']]
)]
class Association
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['association:read', 'membre:read', 'role_assignment:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['association:read', 'association:write', 'membre:read', 'role_assignment:read'])]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['association:read', 'association:write'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Fiangonana::class, inversedBy: 'associations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['association:read', 'association:write'])]
    private ?Fiangonana $fiangonana = null;

    #[ORM\OneToMany(mappedBy: 'association', targetEntity: SousGroupe::class, cascade: ['persist', 'remove'])]
    #[Groups(['association:read'])]
    private Collection $sousGroupes;

    #[ORM\ManyToMany(targetEntity: Membre::class, mappedBy: 'associations')]
    private Collection $membres;

    public function __construct()
    {
        $this->sousGroupes = new ArrayCollection();
        $this->membres = new ArrayCollection();
    }

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

    public function getFiangonana(): ?Fiangonana
    {
        return $this->fiangonana;
    }

    public function setFiangonana(?Fiangonana $fiangonana): self
    {
        $this->fiangonana = $fiangonana;
        return $this;
    }

    /**
     * @return Collection<int, SousGroupe>
     */
    public function getSousGroupes(): Collection
    {
        return $this->sousGroupes;
    }

    public function addSousGroupe(SousGroupe $sousGroupe): self
    {
        if (!$this->sousGroupes->contains($sousGroupe)) {
            $this->sousGroupes->add($sousGroupe);
            $sousGroupe->setAssociation($this);
        }
        return $this;
    }

    public function removeSousGroupe(SousGroupe $sousGroupe): self
    {
        if ($this->sousGroupes->removeElement($sousGroupe)) {
            if ($sousGroupe->getAssociation() === $this) {
                $sousGroupe->setAssociation(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Membre>
     */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function addMembre(Membre $membre): self
    {
        if (!$this->membres->contains($membre)) {
            $this->membres->add($membre);
            $membre->addAssociation($this);
        }
        return $this;
    }

    public function removeMembre(Membre $membre): self
    {
        if ($this->membres->removeElement($membre)) {
            $membre->removeAssociation($this);
        }
        return $this;
    }
}
