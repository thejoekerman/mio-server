<?php

namespace App\Repository;

use App\Entity\SyncDeletion;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SyncDeletion> */
class SyncDeletionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SyncDeletion::class);
    }

    public function findOneForUser(User $user, string $entityType, string $entityId): ?SyncDeletion
    {
        return $this->findOneBy([
            'user' => $user,
            'entityType' => $entityType,
            'entityId' => $entityId,
        ]);
    }

    /** @return list<SyncDeletion> */
    public function findChangedForUser(User $user, int $revision): array
    {
        return $this->createQueryBuilder('deletion')
            ->andWhere('deletion.user = :user')
            ->andWhere('deletion.revision > :revision')
            ->setParameter('user', $user)
            ->setParameter('revision', $revision)
            ->orderBy('deletion.revision', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<SyncDeletion> */
    public function findAllForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['revision' => 'ASC']);
    }

    /** @return list<SyncDeletion> */
    public function findExpired(\DateTimeImmutable $cutoff): array
    {
        return $this->createQueryBuilder('deletion')
            ->andWhere('deletion.deletedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('deletion.user', 'ASC')
            ->addOrderBy('deletion.revision', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
