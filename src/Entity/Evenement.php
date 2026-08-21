<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'evenement')]
#[ApiResource(
    normalizationContext: ['groups' => ['evenement:read']],
    denormalizationContext: ['groups' => ['evenement:write']]
)]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['evenement:read', 'fiangonana:read', 'groupe:read', 'association:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['evenement:read', 'evenement:write', 'fiangonana:read', 'groupe:read', 'association:read'])]
    private ?string $nom = null;

    #[ORM\ManyToOne(targetEntity: TypeEvenement::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['evenement:read', 'evenement:write', 'fiangonana:read', 'groupe:read', 'association:read'])]
    private ?TypeEvenement $typeEvenement = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['evenement:read', 'evenement:write'])]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['evenement:read', 'evenement:write'])]
    private ?string $compteRendu = null;

    #[ORM\ManyToMany(targetEntity: Note::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'evenement_note')]
    #[Groups(['evenement:read', 'evenement:write'])]
    private Collection $notes;

    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: Media::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['evenement:read', 'evenement:write'])]
    private Collection $medias;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['evenement:read', 'evenement:write'])]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['evenement:read', 'evenement:write'])]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['evenement:read', 'evenement:write'])]
    private ?string $lieu = null;

    #[ORM\ManyToOne(targetEntity: Fiangonana::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['evenement:read', 'evenement:write'])]
    private ?Fiangonana $fiangonana = null;

    #[ORM\ManyToOne(targetEntity: Groupe::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['evenement:read', 'evenement:write'])]
    private ?Groupe $groupe = null;

    #[ORM\ManyToOne(targetEntity: Association::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['evenement:read', 'evenement:write'])]
    private ?Association $association = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['evenement:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->notes = new ArrayCollection();
        $this->medias = new ArrayCollection();
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

    public function getTypeEvenement(): ?TypeEvenement
    {
        return $this->typeEvenement;
    }

    public function setTypeEvenement(?TypeEvenement $typeEvenement): self
    {
        $this->typeEvenement = $typeEvenement;
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

    public function getCompteRendu(): ?string
    {
        return $this->compteRendu;
    }

    public function setCompteRendu(?string $compteRendu): self
    {
        $this->compteRendu = $compteRendu;
        return $this;
    }

    /**
     * @return Collection<int, Note>
     */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    public function addNote(Note $note): self
    {
        if (!$this->notes->contains($note)) {
            $this->notes->add($note);
        }
        return $this;
    }

    public function removeNote(Note $note): self
    {
        $this->notes->removeElement($note);
        return $this;
    }

    /**
     * Legacy getter helper for backwards compatibility returning array of strings
     */
    public function getNotesAsArray(): array
    {
        $res = [];
        foreach ($this->notes as $note) {
            $res[] = $note->getContenu();
        }
        return $res;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getMedias(): Collection
    {
        return $this->medias;
    }

    public function addMedia(Media $media): self
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setEvenement($this);
        }
        return $this;
    }

    public function removeMedia(Media $media): self
    {
        if ($this->medias->removeElement($media)) {
            if ($media->getEvenement() === $this) {
                $media->setEvenement(null);
            }
        }
        return $this;
    }

    /**
     * Legacy getter helper returning array of media URLs for API Platform / serialized compatibility
     */
    public function getMediaUrls(): array
    {
        $urls = [];
        foreach ($this->medias as $media) {
            $urls[] = $media->getUrl();
        }
        return $urls;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): self
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): self
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): self
    {
        $this->lieu = $lieu;
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

    public function getGroupe(): ?Groupe
    {
        return $this->groupe;
    }

    public function setGroupe(?Groupe $groupe): self
    {
        $this->groupe = $groupe;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
