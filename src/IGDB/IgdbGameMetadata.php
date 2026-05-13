<?php

namespace App\IGDB;

final readonly class IgdbGameMetadata
{
    public function __construct(
        public int $igdbId,
        public string $name,
        public ?string $url,
        public ?string $coverImageId,
        public ?\DateTimeImmutable $firstReleaseDate,
        public array $developers = [],
        public array $publishers = [],
        public array $themes = [],
        public array $gameModes = [],
    ) {
    }

    public function coverUrl(): ?string
    {
        if (null === $this->coverImageId || '' === trim($this->coverImageId)) {
            return null;
        }

        return sprintf(
            'https://images.igdb.com/igdb/image/upload/t_cover_big/%s.webp',
            $this->coverImageId,
        );
    }
}
