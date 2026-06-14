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

    #[ORM\Column(nullable: true)]
    private ?int $releaseYear = null;

    #[ORM\Column(type: Types::JSON)]
    private array $developers = [];

    #[ORM\Column(type: Types::JSON)]
    private array $publishers = [];

    #[ORM\Column(type: Types::JSON)]
    private array $genres = [];

    #[ORM\Column(type: Types::JSON)]
    private array $themes = [];

    #[ORM\Column(type: Types::JSON)]
    private array $gameModes = [];

    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $cover = null;

    #[ORM\Column(type: Types::JSON)]
    private array $externalReferences = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $playtimeEstimates = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $metadataReviewedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $revision = 0;

    /** @var Collection<int, Journey> */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Journey::class, cascade: ['persist'])]
    private Collection $journeys;

    public function __construct()
    {
        $this->journeys = new ArrayCollection();
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
    public function getReleaseYear(): ?int
    {
        return $this->releaseYear;
    }
    public function setReleaseYear(?int $releaseYear): static
    {
        $this->releaseYear = $releaseYear;
        return $this;
    }
    public function getDevelopers(): array
    {
        return $this->developers;
    }
    public function setDevelopers(array $developers): static
    {
        $this->developers = $this->strings($developers);
        return $this;
    }
    public function getPublishers(): array
    {
        return $this->publishers;
    }
    public function setPublishers(array $publishers): static
    {
        $this->publishers = $this->strings($publishers);
        return $this;
    }
    public function getGenres(): array
    {
        return $this->genres;
    }
    public function setGenres(array $genres): static
    {
        $this->genres = $this->strings($genres);
        return $this;
    }
    public function getThemes(): array
    {
        return $this->themes;
    }
    public function setThemes(array $themes): static
    {
        $this->themes = $this->strings($themes);
        return $this;
    }
    public function getGameModes(): array
    {
        return $this->gameModes;
    }
    public function setGameModes(array $gameModes): static
    {
        $this->gameModes = $this->strings($gameModes);
        return $this;
    }
    public function getTags(): array
    {
        return $this->tags;
    }
    public function setTags(array $tags): static
    {
        $this->tags = $this->strings($tags);
        return $this;
    }
    public function getCover(): ?array
    {
        return $this->cover;
    }
    public function setCover(?array $cover): static
    {
        $this->cover = $cover;
        return $this;
    }
    public function getExternalReferences(): array
    {
        return $this->externalReferences;
    }
    public function setExternalReferences(array $externalReferences): static
    {
        $this->externalReferences = $externalReferences;
        return $this;
    }
    public function getPlaytimeEstimates(): ?array
    {
        return $this->playtimeEstimates;
    }
    public function setPlaytimeEstimates(?array $playtimeEstimates): static
    {
        $this->playtimeEstimates = $playtimeEstimates;
        return $this;
    }
    public function getMetadataReviewedAt(): ?\DateTimeImmutable
    {
        return $this->metadataReviewedAt;
    }
    public function setMetadataReviewedAt(?\DateTimeImmutable $metadataReviewedAt): static
    {
        $this->metadataReviewedAt = $metadataReviewedAt;
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

    /** @return Collection<int, Journey> */
    public function getJourneys(): Collection
    {
        return $this->journeys;
    }

    private function strings(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => is_string($value) ? trim($value) : '', $values),
        )));
    }
}
