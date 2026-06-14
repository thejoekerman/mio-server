<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * @return list<Game>
     */
    public function findAllForUser(User $user): array
    {
        return $this->createQueryBuilder('game')
            ->andWhere('game.user = :user')
            ->setParameter('user', $user)
            ->orderBy('game.revision', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Game>
     */
    public function findChangedForUser(User $user, int $revision): array
    {
        return $this->createQueryBuilder('game')
            ->andWhere('game.user = :user')
            ->andWhere('game.revision > :revision')
            ->setParameter('user', $user)
            ->setParameter('revision', $revision)
            ->orderBy('game.revision', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUserById(User $user, string $id): ?Game
    {
        return $this->createQueryBuilder('game')
            ->andWhere('game.user = :user')
            ->andWhere('game.id = :id')
            ->setParameter('user', $user)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Game>
     */
    public function findVisibleForUserWithJourneys(User $user): array
    {
        return $this->createQueryBuilder('game')
            ->leftJoin('game.journeys', 'journey')
            ->leftJoin('journey.logEntries', 'logEntry')
            ->addSelect('journey', 'logEntry')
            ->andWhere('game.user = :user')
            ->andWhere('game.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('game.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
