<?php

namespace App\Service;

final readonly class AiFeatureAvailability
{
    public function __construct(
        private ?string $geminiApiKey,
        private ?string $lmStudioHostUrl,
        private string $appEnv,
        private ?string $aiProvider = null,
    ) {
    }

    public function reviewDraftAvailable(): bool
    {
        return $this->providerAvailable($this->resolveProvider());
    }

    public function playNextAvailable(): bool
    {
        return $this->providerAvailable($this->resolveProvider());
    }

    public function resolveProvider(): string
    {
        $provider = trim((string) $this->aiProvider);

        if ('' !== $provider) {
            return $provider;
        }

        return 'dev' === $this->appEnv ? 'lmstudio' : 'gemini';
    }

    private function providerAvailable(string $provider): bool
    {
        return match ($provider) {
            'lmstudio' => null !== $this->lmStudioHostUrl && '' !== trim($this->lmStudioHostUrl),
            'gemini' => null !== $this->geminiApiKey && '' !== trim($this->geminiApiKey),
            default => false,
        };
    }
}
