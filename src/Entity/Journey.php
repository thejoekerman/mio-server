<?php

namespace App\Entity;

use App\Repository\JourneyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JourneyRepository::class)]
class Journey
{
    #[ORM\Id]
    #[ORM\Column(length: 80)]
    private string $id = '';

    #[ORM\ManyToOne(inversedBy: 'journeys')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\Column(length: 32)]
    private string $status = 'backlog';

    #[ORM\Column(length: 120)]
    private string $platform = '';

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $ownershipType = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $priority = null;

    #[ORM\Column(nullable: true)]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $review = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 1, nullable: true)]
    private ?string $playTimeHours = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pausedAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nudgeAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $revision = 0;

    /**
     * @var Collection<int, LogEntry>
     */
    #[ORM\OneToMany(mappedBy: 'journey', targetEntity: LogEntry::class, cascade: ['persist'])]
    private Collection $logEntries;

    public function __construct()
    {
        $this->logEntries = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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
    public function getGame(): ?Game
    {
        return $this->game;
    }
    public function setGame(?Game $game): static
    {
        $this->game = $game;
        return $this;
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }
    public function getPlatform(): string
    {
        return $this->platform;
    }
    public function setPlatform(string $platform): static
    {
        $this->platform = $platform;
        return $this;
    }
    public function getOwnershipType(): ?string
    {
        return $this->ownershipType;
    }
    public function setOwnershipType(?string $ownershipType): static
    {
        $this->ownershipType = $ownershipType;
        return $this;
    }
    public function getPriority(): ?string
    {
        return $this->priority;
    }
    public function setPriority(?string $priority): static
    {
        $this->priority = $priority;
        return $this;
    }
    public function getRating(): ?int
    {
        return $this->rating;
    }
    public function setRating(?int $rating): static
    {
        $this->rating = $rating;
        return $this;
    }
    public function getReview(): string
    {
        return $this->review;
    }
    public function setReview(string $review): static
    {
        $this->review = $review;
        return $this;
    }
    public function getPlayTimeHours(): ?string
    {
        return $this->playTimeHours;
    }
    public function setPlayTimeHours(?string $playTimeHours): static
    {
        $this->playTimeHours = $playTimeHours;
        return $this;
    }
    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }
    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }
    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }
    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;
        return $this;
    }
    public function getPausedAt(): ?\DateTimeImmutable
    {
        return $this->pausedAt;
    }
    public function setPausedAt(?\DateTimeImmutable $pausedAt): static
    {
        $this->pausedAt = $pausedAt;
        return $this;
    }
    public function getNudgeAt(): ?\DateTimeImmutable
    {
        return $this->nudgeAt;
    }
    public function setNudgeAt(?\DateTimeImmutable $nudgeAt): static
    {
        $this->nudgeAt = $nudgeAt;
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

    /** @return Collection<int, LogEntry> */
    public function getLogEntries(): Collection
    {
        return $this->logEntries;
    }
}
