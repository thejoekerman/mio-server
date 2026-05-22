<?php

namespace App\Message;

final class EnrichIgdbMetadataMessage
{
    public function __construct(
        public readonly int $userId,
        public readonly int $limit = 100,
    ) {
    }
}
