<?php

namespace App\AI;

final readonly class GameDiscoveryRecommendation
{
    /**
     * @param list<string> $platforms
     */
    public function __construct(
        public string $title,
        public array $platforms,
        public string $reason,
        public ?string $igdbUrl,
        public ?int $ttbNormallySeconds,
    ) {
    }
}
