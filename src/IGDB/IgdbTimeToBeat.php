<?php

namespace App\IGDB;

final readonly class IgdbTimeToBeat
{
    public function __construct(
        public int $gameId,
        public ?int $hastilySeconds,
        public ?int $normallySeconds,
        public ?int $completelySeconds,
        public ?int $count,
        public ?\DateTimeImmutable $updatedAt,
    ) {
    }
}
