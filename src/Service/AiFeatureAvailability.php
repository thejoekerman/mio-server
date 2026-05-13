<?php

namespace App\Service;

final readonly class AiFeatureAvailability
{
    public function __construct(
        private ?string $geminiApiKey,
        private ?string $lmStudioHostUrl,
        private string $appEnv,
        private ?string $playNextProvider = null,
    ) {
    }

    public function reviewDraftAvailable(): bool
    {
        return null !== $this->geminiApiKey && '' !== trim($this->geminiApiKey);
    }

    public function playNextAvailable(): bool
    {
        return match ($this->resolvePlayNextProvider()) {
            'lmstudio' => null !== $this->lmStudioHostUrl && '' !== trim($this->lmStudioHostUrl),
            'gemini' => $this->reviewDraftAvailable(),
            default => false,
        };
    }

    private function resolvePlayNextProvider(): string
    {
        $provider = trim((string) $this->playNextProvider);

        if ('' !== $provider) {
            return $provider;
        }

        return 'dev' === $this->appEnv ? 'lmstudio' : 'gemini';
    }
}
