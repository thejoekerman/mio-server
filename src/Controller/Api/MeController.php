<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\AiFeatureAvailability;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/me', name: 'api_me', methods: ['GET'])]
class MeController extends AbstractController
{
    public function __invoke(AiFeatureAvailability $aiFeatureAvailability): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Authentication required.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $reviewDraft = $user->getAiUsage() && $aiFeatureAvailability->reviewDraftAvailable();

        return $this->json([
            'version' => 3,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'displayName' => $user->getDisplayName(),
            ],
            'capabilities' => [
                'reviewDraft' => $reviewDraft,
            ],
        ]);
    }
}
