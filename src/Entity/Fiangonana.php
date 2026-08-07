<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'fiangonana')]
#[ApiResource(
    normalizationContext: ['groups' => ['fiangonana:read']],
    denormalizationContext: ['groups' => ['fiangonana:write']]
)]
class Fiangonana
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['fiangonana:read', 'membre:read', 'groupe:read', 'association:read', 'role_assignment:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['fiangonana:read', 'fiangonana:write', 'membre:read', 'groupe:read', 'association:read', 'role_assignment:read'])]
    private ?string $nom = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['fiangonana:read', 'fiangonana:write', 'membre:read'])]
    private ?string $code = null;

    #[ORM\OneToMany(mappedBy: 'fiangonana', targetEntity: Membre::class)]
    private Collection $membres;

    #[ORM\OneToMany(mappedBy: 'fiangonana', targetEntity: Groupe::class)]
    private Collection $groupes;

    #[ORM\OneToMany(mappedBy: 'fiangonana', targetEntity: Association::class)]
    private Collection $associations;

    public function __construct()
    {
        $this->membres = new ArrayCollection();
        $this->groupes = new ArrayCollection();
        $this->associations = new ArrayCollection();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
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
            $membre->setFiangonana($this);
        }
        return $this;
    }

    public function removeMembre(Membre $membre): self
    {
        if ($this->membres->removeElement($membre)) {
            if ($membre->getFiangonana() === $this) {
                $membre->setFiangonana(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Groupe>
     */
    public function getGroupes(): Collection
    {
        return $this->groupes;
    }

    /**
     * @return Collection<int, Association>
     */
    public function getAssociations(): Collection
    {
        return $this->associations;
    }
}
