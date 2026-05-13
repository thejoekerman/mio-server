<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, SyncToken>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: SyncToken::class, cascade: ['persist'])]
    private Collection $syncTokens;

    /**
     * @var Collection<int, Game>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Game::class, cascade: ['persist'])]
    private Collection $games;

    /**
     * @var Collection<int, EarnedTrophy>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: EarnedTrophy::class, cascade: ['persist'])]
    private Collection $earnedTrophies;

    public function __construct()
    {
        $this->syncTokens = new ArrayCollection();
        $this->games = new ArrayCollection();
        $this->earnedTrophies = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName;

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

    /**
     * @return Collection<int, SyncToken>
     */
    public function getSyncTokens(): Collection
    {
        return $this->syncTokens;
    }

    public function addSyncToken(SyncToken $syncToken): static
    {
        if (!$this->syncTokens->contains($syncToken)) {
            $this->syncTokens->add($syncToken);
            $syncToken->setUser($this);
        }

        return $this;
    }

    public function removeSyncToken(SyncToken $syncToken): static
    {
        if ($this->syncTokens->removeElement($syncToken) && $syncToken->getUser() === $this) {
            $syncToken->setUser(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Game>
     */
    public function getGames(): Collection
    {
        return $this->games;
    }

    public function addGame(Game $game): static
    {
        if (!$this->games->contains($game)) {
            $this->games->add($game);
            $game->setUser($this);
        }

        return $this;
    }

    public function removeGame(Game $game): static
    {
        if ($this->games->removeElement($game) && $game->getUser() === $this) {
            $game->setUser(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, EarnedTrophy>
     */
    public function getEarnedTrophies(): Collection
    {
        return $this->earnedTrophies;
    }

    public function addEarnedTrophy(EarnedTrophy $earnedTrophy): static
    {
        if (!$this->earnedTrophies->contains($earnedTrophy)) {
            $this->earnedTrophies->add($earnedTrophy);
            $earnedTrophy->setUser($this);
        }

        return $this;
    }

    public function removeEarnedTrophy(EarnedTrophy $earnedTrophy): static
    {
        if ($this->earnedTrophies->removeElement($earnedTrophy) && $earnedTrophy->getUser() === $this) {
            $earnedTrophy->setUser(null);
        }

        return $this;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email ?? sprintf('user:%d', $this->id ?? 0);
    }
}
