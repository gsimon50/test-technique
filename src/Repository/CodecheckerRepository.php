<?php

namespace App\Repository;

use App\Entity\Codechecker;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Codechecker>
 */
class CodecheckerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Codechecker::class);
    }


    public function findCodeByUserId(int $userId) : ?Codechecker{
        return $this->createQueryBuilder('c')
        ->andWhere('c.id_user = :id_user')
        ->setParameter('id_user', $userId)
        ->getQuery()
        ->setMaxResults(1)->getOneOrNullResult();
    }
}
