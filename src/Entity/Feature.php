<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'feature')]
#[ApiResource(
    normalizationContext: ['groups' => ['feature:read']],
    denormalizationContext: ['groups' => ['feature:write']]
)]
class Feature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['feature:read', 'permission:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Groups(['feature:read', 'feature:write', 'permission:read'])]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Groups(['feature:read', 'feature:write', 'permission:read'])]
    private ?string $label = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['feature:read', 'feature:write', 'permission:read'])]
    private ?string $category = null; // e.g., ADMIN_MENU, MEMBER_SPACE, API_ACTION

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['feature:read', 'feature:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['feature:read', 'feature:write', 'permission:read'])]
    private ?string $targetRoute = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['feature:read', 'feature:write', 'permission:read'])]
    private ?string $icon = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['feature:read', 'feature:write', 'permission:read'])]
    private int $sortOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtoupper($code);
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category !== null ? strtoupper($category) : null;
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

    public function getTargetRoute(): ?string
    {
        return $this->targetRoute;
    }

    public function setTargetRoute(?string $targetRoute): self
    {
        $this->targetRoute = $targetRoute;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }
}
