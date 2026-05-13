<?php

namespace App\Repository;

use App\Entity\SyncToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SyncToken>
 */
class SyncTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SyncToken::class);
    }

    public function findActiveByPlainToken(string $plainToken): ?SyncToken
    {
        return $this->createQueryBuilder('sync_token')
            ->andWhere('sync_token.tokenHash = :tokenHash')
            ->andWhere('sync_token.revokedAt IS NULL')
            ->setParameter('tokenHash', SyncToken::hashPlainToken($plainToken))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
