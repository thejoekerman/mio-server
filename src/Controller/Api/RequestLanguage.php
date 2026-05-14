<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\Request;

trait RequestLanguage
{
    private function resolveRequestLanguage(Request $request): string
    {
        if ('' === trim($request->getContent())) {
            return 'en';
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return 'en';
        }

        $language = is_array($payload) && isset($payload['language']) && is_string($payload['language'])
            ? strtolower(trim($payload['language']))
            : '';

        return in_array($language, ['de', 'en'], true) ? $language : 'en';
    }
}
