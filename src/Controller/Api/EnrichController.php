<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\EnrichAlreadyRunningException;
use App\Message\EnrichIgdbMetadataMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/enrich', name: 'api_enrich', methods: ['POST'])]
class EnrichController extends AbstractController
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->messageBus->dispatch(new EnrichIgdbMetadataMessage((int) $user->getId()));

            return $this->json([], Response::HTTP_ACCEPTED);
        } catch (HandlerFailedException $e) {
            foreach ($e->getWrappedExceptions() as $nested) {
                if ($nested instanceof EnrichAlreadyRunningException) {
                    return $this->json(['error' => 'Enrichment is already running.'], Response::HTTP_LOCKED);
                }
            }

            return $this->json(['error' => 'Enrichment failed.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
