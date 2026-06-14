<?php

namespace App\Repository;

use App\Entity\LogEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LogEntry>
 */
class LogEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LogEntry::class);
    }

    /**
     * @return list<LogEntry>
     */
    public function findAllForUser(User $user): array
    {
        return $this->createQueryBuilder('log_entry')
            ->innerJoin('log_entry.journey', 'journey')
            ->innerJoin('journey.game', 'game')
            ->andWhere('game.user = :user')
            ->setParameter('user', $user)
            ->orderBy('log_entry.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<LogEntry>
     */
    public function findChangedForUser(User $user, int $revision): array
    {
        return $this->createQueryBuilder('log_entry')
            ->innerJoin('log_entry.journey', 'journey')
            ->innerJoin('journey.game', 'game')
            ->andWhere('game.user = :user')
            ->andWhere('log_entry.revision > :revision')
            ->setParameter('user', $user)
            ->setParameter('revision', $revision)
            ->orderBy('log_entry.revision', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUserById(User $user, string $id): ?LogEntry
    {
        return $this->createQueryBuilder('log_entry')
            ->innerJoin('log_entry.journey', 'journey')
            ->innerJoin('journey.game', 'game')
            ->andWhere('game.user = :user')
            ->andWhere('log_entry.id = :id')
            ->setParameter('user', $user)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
