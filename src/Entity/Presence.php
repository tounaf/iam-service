<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'presence')]
#[ApiResource(
    normalizationContext: ['groups' => ['presence:read']],
    denormalizationContext: ['groups' => ['presence:write']]
)]
class Presence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['presence:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Membre::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['presence:read', 'presence:write'])]
    private ?Membre $membre = null;

    #[ORM\Column(length: 255)]
    #[Groups(['presence:read', 'presence:write'])]
    private ?string $activityName = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['presence:read', 'presence:write'])]
    private ?\DateTimeImmutable $scannedAt = null;

    #[ORM\ManyToOne(targetEntity: Membre::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['presence:read', 'presence:write'])]
    private ?Membre $scannedBy = null;

    public function __construct()
    {
        $this->scannedAt = new \DateTimeImmutable();
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

    public function getActivityName(): ?string
    {
        return $this->activityName;
    }

    public function setActivityName(string $activityName): self
    {
        $this->activityName = $activityName;
        return $this;
    }

    public function getScannedAt(): ?\DateTimeImmutable
    {
        return $this->scannedAt;
    }

    public function setScannedAt(\DateTimeImmutable $scannedAt): self
    {
        $this->scannedAt = $scannedAt;
        return $this;
    }

    public function getScannedBy(): ?Membre
    {
        return $this->scannedBy;
    }

    public function setScannedBy(?Membre $scannedBy): self
    {
        $this->scannedBy = $scannedBy;
        return $this;
    }
}
