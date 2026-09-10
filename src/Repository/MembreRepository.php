<?php

namespace App\Repository;

use App\Entity\Membre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Membre>
 */
class MembreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Membre::class);
    }

    /**
     * Search and paginate membres based on filters.
     */
    public function findBySearchAndPaginate(
        ?string $search = null,
        ?int $fiangonanaId = null,
        ?int $groupeId = null,
        ?int $associationId = null,
        int $page = 1,
        int $limit = 50
    ): Paginator {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.id', 'DESC');

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $qb->andWhere('m.nom LIKE :search OR m.prenom LIKE :search OR CONCAT(m.prenom, CONCAT(\' \', m.nom)) LIKE :search OR CONCAT(m.nom, CONCAT(\' \', m.prenom)) LIKE :search')
               ->setParameter('search', $term);
        }

        if ($fiangonanaId !== null && $fiangonanaId > 0) {
            $qb->andWhere('m.fiangonana = :fiangonanaId')
               ->setParameter('fiangonanaId', $fiangonanaId);
        }

        if ($groupeId !== null && $groupeId > 0) {
            $qb->andWhere('m.zoneGeographique = :groupeId')
               ->setParameter('groupeId', $groupeId);
        }

        if ($associationId !== null && $associationId > 0) {
            $qb->join('m.associations', 'a')
               ->andWhere('a.id = :associationId')
               ->setParameter('associationId', $associationId);
        }

        $query = $qb->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($query);
    }
}
