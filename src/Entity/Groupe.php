<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'groupe')]
#[ApiResource(
    normalizationContext: ['groups' => ['groupe:read']],
    denormalizationContext: ['groups' => ['groupe:write']]
)]
class Groupe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['groupe:read', 'membre:read', 'role_assignment:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['groupe:read', 'groupe:write', 'membre:read', 'role_assignment:read'])]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['groupe:read', 'groupe:write'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Fiangonana::class, inversedBy: 'groupes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['groupe:read', 'groupe:write'])]
    private ?Fiangonana $fiangonana = null;

    #[ORM\OneToMany(mappedBy: 'zoneGeographique', targetEntity: Membre::class)]
    private Collection $membres;

    public function __construct()
    {
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
            $membre->setZoneGeographique($this);
        }
        return $this;
    }

    public function removeMembre(Membre $membre): self
    {
        if ($this->membres->removeElement($membre)) {
            if ($membre->getZoneGeographique() === $this) {
                $membre->setZoneGeographique(null);
            }
        }
        return $this;
    }
}
