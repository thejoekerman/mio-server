<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\SyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sync', name: 'api_sync', methods: ['POST'])]
class SyncController extends AbstractController
{
    public function __invoke(Request $request, SyncService $syncService): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $this->json([
                'error' => 'Invalid JSON payload.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            return $this->json($syncService->sync($user, $payload));
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'error' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
