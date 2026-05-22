<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
            ->orderBy('game.updatedAt', 'DESC')
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
    public function findVisibleForUserWithLogs(User $user): array
    {
        return $this->createQueryBuilder('game')
            ->leftJoin('game.logEntries', 'logEntry')
            ->addSelect('logEntry')
            ->andWhere('game.user = :user')
            ->andWhere('game.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('game.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Game>
     */
    public function findMissingIgdbMetadata(): array
    {
        return $this->missingIgdbMetadataQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Game>
     */
    public function findMissingIgdbMetadataForUser(User $user, int $limit): array
    {
        return $this->missingIgdbMetadataQueryBuilder()
            ->andWhere('game.user = :user')
            ->setParameter('user', $user)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function missingIgdbMetadataQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('game')
            ->andWhere('game.deletedAt IS NULL')
            ->andWhere('game.igdbId IS NOT NULL')
            ->andWhere('game.coverUrl IS NULL OR game.igdbUrl IS NULL OR game.igdbTtbCount IS NULL OR game.igdbDevelopers IS NULL OR game.igdbPublishers IS NULL OR game.igdbThemes IS NULL OR game.igdbGameModes IS NULL OR game.releaseYear IS NULL')
            ->orderBy('game.updatedAt', 'DESC');
    }
}
