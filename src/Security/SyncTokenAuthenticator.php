<?php

namespace App\Security;

use App\Entity\SyncToken;
use App\Repository\SyncTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class SyncTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly SyncTokenRepository $syncTokenRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api');
    }

    public function authenticate(Request $request): Passport
    {
        $header = $request->headers->get('Authorization');

        if (!$header || !preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            throw new CustomUserMessageAuthenticationException('Missing sync token.');
        }

        $plainToken = trim($matches[1]);

        if ($plainToken === '') {
            throw new CustomUserMessageAuthenticationException('Missing sync token.');
        }

        $syncToken = $this->syncTokenRepository->findActiveByPlainToken($plainToken);

        if (!$syncToken instanceof SyncToken || null === $syncToken->getUser()) {
            throw new CustomUserMessageAuthenticationException('Invalid sync token.');
        }

        $syncToken->setLastUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new SelfValidatingPassport(
            new UserBadge(
                sprintf('sync-token:%d', $syncToken->getId() ?? 0),
                static fn () => $syncToken->getUser(),
            ),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            [
                'error' => $exception->getMessageKey(),
            ],
            Response::HTTP_UNAUTHORIZED,
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(
            [
                'error' => 'Authentication required.',
            ],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
