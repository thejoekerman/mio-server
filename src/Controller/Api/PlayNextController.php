<?php

namespace App\Controller\Api;

use App\AI\PlayNextRecommendationService;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Service\AiFeatureAvailability;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ai/play-next', name: 'api_ai_play_next', methods: ['POST'])]
final class PlayNextController extends AbstractController
{
    public function __invoke(
        GameRepository $gameRepository,
        PlayNextRecommendationService $playNextRecommendationService,
        AiFeatureAvailability $aiFeatureAvailability,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Authentication required.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$aiFeatureAvailability->playNextAvailable()) {
            return $this->json([
                'error' => 'Play-next recommendations are not available on this backend.',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        $games = $gameRepository->findVisibleForUserWithLogs($user);

        try {
            $recommendations = $playNextRecommendationService->recommend($games);
        } catch (\RuntimeException $exception) {
            return $this->json([
                'error' => $exception->getMessage(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'recommendations' => array_map(
                static fn ($recommendation): array => [
                    'slot' => $recommendation->slot,
                    'gameId' => $recommendation->gameId,
                    'title' => $recommendation->title,
                    'reason' => $recommendation->reason,
                ],
                $recommendations,
            ),
        ]);
    }
}
