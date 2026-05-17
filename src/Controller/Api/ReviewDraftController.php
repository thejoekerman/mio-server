<?php

namespace App\Controller\Api;

use App\AI\ReviewDraftingService;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Service\AiFeatureAvailability;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ai/review-draft/{gameId}', name: 'api_ai_review_draft', methods: ['POST'])]
final class ReviewDraftController extends AbstractController
{
    use RequestLanguage;

    public function __invoke(
        Request $request,
        string $gameId,
        GameRepository $gameRepository,
        ReviewDraftingService $reviewDraftingService,
        AiFeatureAvailability $aiFeatureAvailability,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Authentication required.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$user->getAiUsage() || !$aiFeatureAvailability->reviewDraftAvailable()) {
            return $this->json([
                'error' => 'Review drafting is not available on this backend.',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        $game = $gameRepository->findOneForUserById($user, $gameId);

        if (null === $game) {
            return $this->json([
                'error' => 'Game not found.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json([
            'gameId' => $game->getId(),
            'draft' => $reviewDraftingService->draftReview(
                $game,
                null,
                $this->resolveRequestLanguage($request),
            ),
        ]);
    }
}
