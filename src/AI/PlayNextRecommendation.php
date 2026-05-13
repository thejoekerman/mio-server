<?php

namespace App\AI;

final readonly class PlayNextRecommendation
{
    public function __construct(
        public string $slot,
        public string $gameId,
        public string $title,
        public string $reason,
    ) {
    }
}
