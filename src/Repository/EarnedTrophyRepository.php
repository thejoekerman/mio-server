<?php

namespace App\Repository;

use App\Entity\EarnedTrophy;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EarnedTrophy>
 */
class EarnedTrophyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EarnedTrophy::class);
    }

    /**
     * @return list<EarnedTrophy>
     */
    public function findAllForUser(User $user): array
    {
        return $this->createQueryBuilder('earned_trophy')
            ->andWhere('earned_trophy.user = :user')
            ->setParameter('user', $user)
            ->orderBy('earned_trophy.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUserById(User $user, string $id): ?EarnedTrophy
    {
        $ids = [$id];

        if (null !== $user->getId()) {
            $ids[] = sprintf('%d:%s', $user->getId(), $id);
        }

        return $this->createQueryBuilder('earned_trophy')
            ->andWhere('earned_trophy.user = :user')
            ->andWhere('earned_trophy.id IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', array_values(array_unique($ids)))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
