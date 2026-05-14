<?php

namespace App\Controller\Api;

use App\AI\GameDiscoveryRecommendationService;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Service\AiFeatureAvailability;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ai/discover-games', name: 'api_ai_discover_games', methods: ['POST'])]
final class GameDiscoveryController extends AbstractController
{
    use RequestLanguage;

    public function __invoke(
        Request $request,
        GameRepository $gameRepository,
        GameDiscoveryRecommendationService $gameDiscoveryRecommendationService,
        AiFeatureAvailability $aiFeatureAvailability,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Authentication required.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$aiFeatureAvailability->gameDiscoveryAvailable()) {
            return $this->json([
                'error' => 'Game discovery recommendations are not available on this backend.',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        $games = $gameRepository->findVisibleForUserWithLogs($user);

        try {
            $recommendations = $gameDiscoveryRecommendationService->recommend(
                $games,
                null,
                $this->resolveRequestLanguage($request),
            );
        } catch (\RuntimeException $exception) {
            return $this->json([
                'error' => $exception->getMessage(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'recommendations' => array_map(
                static fn ($recommendation): array => [
                    'title' => $recommendation->title,
                    'platforms' => $recommendation->platforms,
                    'reason' => $recommendation->reason,
                    'igdbUrl' => $recommendation->igdbUrl,
                    'ttbNormallySeconds' => $recommendation->ttbNormallySeconds,
                ],
                $recommendations,
            ),
        ]);
    }
}
