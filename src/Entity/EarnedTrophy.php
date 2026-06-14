<?php

namespace App\Entity;

use App\Repository\EarnedTrophyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EarnedTrophyRepository::class)]
class EarnedTrophy
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $id = '';

    #[ORM\ManyToOne(inversedBy: 'earnedTrophies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 120)]
    private string $trophyId = '';

    #[ORM\Column]
    private \DateTimeImmutable $earnedAt;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $gameId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $revision = 0;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->earnedAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTrophyId(): string
    {
        return $this->trophyId;
    }

    public function setTrophyId(string $trophyId): static
    {
        $this->trophyId = $trophyId;

        return $this;
    }

    public function getEarnedAt(): \DateTimeImmutable
    {
        return $this->earnedAt;
    }

    public function setEarnedAt(\DateTimeImmutable $earnedAt): static
    {
        $this->earnedAt = $earnedAt;

        return $this;
    }

    public function getGameId(): ?string
    {
        return $this->gameId;
    }

    public function setGameId(?string $gameId): static
    {
        $this->gameId = $gameId;

        return $this;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function setRevision(int $revision): static
    {
        $this->revision = $revision;

        return $this;
    }
}
