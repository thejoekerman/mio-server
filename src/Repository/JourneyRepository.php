<?php

namespace App\Repository;

use App\Entity\Journey;
use App\Entity\Game;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Journey> */
class JourneyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Journey::class);
    }

    /** @return list<Journey> */
    public function findAllForUser(User $user): array
    {
        return $this->createQueryBuilder('journey')
            ->innerJoin('journey.game', 'game')
            ->andWhere('game.user = :user')
            ->setParameter('user', $user)
            ->orderBy('journey.revision', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Journey> */
    public function findChangedForUser(User $user, int $revision): array
    {
        return $this->createQueryBuilder('journey')
            ->innerJoin('journey.game', 'game')
            ->andWhere('game.user = :user')
            ->andWhere('journey.revision > :revision')
            ->setParameter('user', $user)
            ->setParameter('revision', $revision)
            ->orderBy('journey.revision', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUserById(User $user, string $id): ?Journey
    {
        return $this->createQueryBuilder('journey')
            ->innerJoin('journey.game', 'game')
            ->andWhere('game.user = :user')
            ->andWhere('journey.id = :id')
            ->setParameter('user', $user)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestVisibleForGame(Game $game): ?Journey
    {
        return $this->createQueryBuilder('journey')
            ->andWhere('journey.game = :game')
            ->andWhere('journey.deletedAt IS NULL')
            ->setParameter('game', $game)
            ->orderBy('journey.updatedAt', 'DESC')
            ->addOrderBy('journey.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
