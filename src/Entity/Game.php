<?php

namespace App\Entity;

use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
class Game
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id = '';

    #[ORM\ManyToOne(inversedBy: 'games')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 32)]
    private string $status = 'backlog';

    #[ORM\Column(nullable: true)]
    private ?int $rating = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 1, nullable: true)]
    private ?string $playTimeHours = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $review = '';

    #[ORM\Column(length: 120)]
    private string $platform = '';

    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(nullable: true)]
    private ?int $igdbId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $igdbUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverUrl = null;

    #[ORM\Column(nullable: true)]
    private ?int $igdbTtbHastilySeconds = null;

    #[ORM\Column(nullable: true)]
    private ?int $igdbTtbNormallySeconds = null;

    #[ORM\Column(nullable: true)]
    private ?int $igdbTtbCompletelySeconds = null;

    #[ORM\Column(nullable: true)]
    private ?int $igdbTtbCount = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $igdbTtbUpdatedAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $igdbDevelopers = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $igdbPublishers = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $igdbThemes = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $igdbGameModes = null;

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

    /**
     * @var Collection<int, LogEntry>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: LogEntry::class, cascade: ['persist'])]
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): static
    {
        $this->rating = $rating;

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

    public function getReview(): string
    {
        return $this->review;
    }

    public function setReview(string $review): static
    {
        $this->review = $review;

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

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): static
    {
        $this->tags = array_values($tags);

        return $this;
    }

    public function getIgdbId(): ?int
    {
        return $this->igdbId;
    }

    public function setIgdbId(?int $igdbId): static
    {
        $this->igdbId = $igdbId;

        return $this;
    }

    public function getIgdbUrl(): ?string
    {
        return $this->igdbUrl;
    }

    public function setIgdbUrl(?string $igdbUrl): static
    {
        $this->igdbUrl = $igdbUrl;

        return $this;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): static
    {
        $this->coverUrl = $coverUrl;

        return $this;
    }

    public function getIgdbTtbHastilySeconds(): ?int
    {
        return $this->igdbTtbHastilySeconds;
    }

    public function setIgdbTtbHastilySeconds(?int $igdbTtbHastilySeconds): static
    {
        $this->igdbTtbHastilySeconds = $igdbTtbHastilySeconds;

        return $this;
    }

    public function getIgdbTtbNormallySeconds(): ?int
    {
        return $this->igdbTtbNormallySeconds;
    }

    public function setIgdbTtbNormallySeconds(?int $igdbTtbNormallySeconds): static
    {
        $this->igdbTtbNormallySeconds = $igdbTtbNormallySeconds;

        return $this;
    }

    public function getIgdbTtbCompletelySeconds(): ?int
    {
        return $this->igdbTtbCompletelySeconds;
    }

    public function setIgdbTtbCompletelySeconds(?int $igdbTtbCompletelySeconds): static
    {
        $this->igdbTtbCompletelySeconds = $igdbTtbCompletelySeconds;

        return $this;
    }

    public function getIgdbTtbCount(): ?int
    {
        return $this->igdbTtbCount;
    }

    public function setIgdbTtbCount(?int $igdbTtbCount): static
    {
        $this->igdbTtbCount = $igdbTtbCount;

        return $this;
    }

    public function getIgdbTtbUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->igdbTtbUpdatedAt;
    }

    public function setIgdbTtbUpdatedAt(?\DateTimeImmutable $igdbTtbUpdatedAt): static
    {
        $this->igdbTtbUpdatedAt = $igdbTtbUpdatedAt;

        return $this;
    }

    public function getIgdbDevelopers(): ?array
    {
        return $this->igdbDevelopers;
    }

    public function setIgdbDevelopers(?array $igdbDevelopers): static
    {
        $this->igdbDevelopers = null === $igdbDevelopers ? null : $this->normalizeStringList($igdbDevelopers);

        return $this;
    }

    public function getIgdbPublishers(): ?array
    {
        return $this->igdbPublishers;
    }

    public function setIgdbPublishers(?array $igdbPublishers): static
    {
        $this->igdbPublishers = null === $igdbPublishers ? null : $this->normalizeStringList($igdbPublishers);

        return $this;
    }

    public function getIgdbThemes(): ?array
    {
        return $this->igdbThemes;
    }

    public function setIgdbThemes(?array $igdbThemes): static
    {
        $this->igdbThemes = null === $igdbThemes ? null : $this->normalizeStringList($igdbThemes);

        return $this;
    }

    public function getIgdbGameModes(): ?array
    {
        return $this->igdbGameModes;
    }

    public function setIgdbGameModes(?array $igdbGameModes): static
    {
        $this->igdbGameModes = null === $igdbGameModes ? null : $this->normalizeStringList($igdbGameModes);

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

    /**
     * @return Collection<int, LogEntry>
     */
    public function getLogEntries(): Collection
    {
        return $this->logEntries;
    }

    public function addLogEntry(LogEntry $logEntry): static
    {
        if (!$this->logEntries->contains($logEntry)) {
            $this->logEntries->add($logEntry);
            $logEntry->setGame($this);
        }

        return $this;
    }

    public function removeLogEntry(LogEntry $logEntry): static
    {
        if ($this->logEntries->removeElement($logEntry) && $logEntry->getGame() === $this) {
            $logEntry->setGame(null);
        }

        return $this;
    }

    private function normalizeStringList(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);

            if ('' !== $trimmed) {
                $normalized[] = $trimmed;
            }
        }

        return array_values(array_unique($normalized));
    }
}
