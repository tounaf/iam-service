<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'membre')]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Patch(),
        new Delete(),
        new Get(
            uriTemplate: '/membres/{id}/qr-code',
            controller: \App\Controller\MembreQrCodeController::class,
            read: false,
            serialize: false
        ),
        new Get(
            uriTemplate: '/membres/{id}/carte',
            controller: \App\Controller\MembreCarteController::class,
            read: false,
            serialize: false
        ),
        new Get(
            uriTemplate: '/membres/{id}/stats',
            controller: \App\Controller\MembreStatsController::class,
            read: false,
            serialize: false
        )
    ],
    normalizationContext: ['groups' => ['membre:read']],
    denormalizationContext: ['groups' => ['membre:write']]
)]
class Membre implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['membre:read', 'fiangonana:read', 'role_assignment:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['membre:read', 'membre:write', 'role_assignment:read'])]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Groups(['membre:read', 'membre:write', 'role_assignment:read'])]
    private ?string $prenom = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['membre:read', 'membre:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['membre:read', 'membre:write'])]
    private ?string $telephone = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['membre:read', 'membre:write'])]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\ManyToOne(targetEntity: Groupe::class, inversedBy: 'membres')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['membre:read', 'membre:write'])]
    private ?Groupe $zoneGeographique = null;

    #[ORM\ManyToMany(targetEntity: Association::class, inversedBy: 'membres')]
    #[ORM\JoinTable(name: 'membre_association')]
    #[Groups(['membre:read', 'membre:write'])]
    private Collection $associations;

    #[ORM\ManyToOne(targetEntity: Fiangonana::class, inversedBy: 'membres')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['membre:read', 'membre:write'])]
    private ?Fiangonana $fiangonana = null;

    #[ORM\OneToMany(mappedBy: 'membre', targetEntity: RoleAssignment::class, cascade: ['persist', 'remove'])]
    #[Groups(['membre:read'])]
    private Collection $roleAssignments;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['membre:read'])]
    private ?string $qrCodeToken = null;

    public function __construct()
    {
        $this->associations = new ArrayCollection();
        $this->roleAssignments = new ArrayCollection();
        $this->roles = ['ROLE_USER'];
        $this->qrCodeToken = bin2hex(random_bytes(16));
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeInterface $dateNaissance): self
    {
        $this->dateNaissance = $dateNaissance;
        return $this;
    }

    public function getZoneGeographique(): ?Groupe
    {
        return $this->zoneGeographique;
    }

    public function setZoneGeographique(?Groupe $zoneGeographique): self
    {
        $this->zoneGeographique = $zoneGeographique;
        return $this;
    }

    /**
     * @return Collection<int, Association>
     */
    public function getAssociations(): Collection
    {
        return $this->associations;
    }

    public function addAssociation(Association $association): self
    {
        if (!$this->associations->contains($association)) {
            $this->associations->add($association);
        }
        return $this;
    }

    public function removeAssociation(Association $association): self
    {
        $this->associations->removeElement($association);
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
     * @return Collection<int, RoleAssignment>
     */
    public function getRoleAssignments(): Collection
    {
        return $this->roleAssignments;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Symfony Security requirements
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getQrCodeToken(): ?string
    {
        return $this->qrCodeToken;
    }

    public function setQrCodeToken(string $qrCodeToken): self
    {
        $this->qrCodeToken = $qrCodeToken;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }
}
