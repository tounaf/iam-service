<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
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

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['evenement:read', 'evenement:write'])]
    private ?array $notes = [];

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['evenement:read', 'evenement:write'])]
    private ?array $mediaUrls = [];

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
        $this->notes = [];
        $this->mediaUrls = [];
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

    public function getNotes(): array
    {
        return $this->notes ?? [];
    }

    public function setNotes(?array $notes): self
    {
        $this->notes = $notes ?? [];
        return $this;
    }

    public function addNote(string $note): self
    {
        $currentNotes = $this->getNotes();
        if (!in_array($note, $currentNotes, true)) {
            $currentNotes[] = $note;
            $this->notes = $currentNotes;
        }
        return $this;
    }

    public function getMediaUrls(): array
    {
        return $this->mediaUrls ?? [];
    }

    public function setMediaUrls(?array $mediaUrls): self
    {
        $this->mediaUrls = $mediaUrls ?? [];
        return $this;
    }

    public function addMediaUrl(string $mediaUrl): self
    {
        $currentMediaUrls = $this->getMediaUrls();
        if (!in_array($mediaUrl, $currentMediaUrls, true)) {
            $currentMediaUrls[] = $mediaUrl;
            $this->mediaUrls = $currentMediaUrls;
        }
        return $this;
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
