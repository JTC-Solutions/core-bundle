<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\Concrete\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use JtcSolutions\Core\Repository\BaseRepository;
use JtcSolutions\Core\Tests\Fixtures\Concrete\Entity\TestEntity;

class ConcreteTestRepository extends BaseRepository
{
    public function __construct(
        EntityManagerInterface $entityManager,
    ) {
        parent::__construct($entityManager, TestEntity::class);
    }

    public function publicHandleJoin(QueryBuilder $queryBuilder, string $alias, string $rootAlias): QueryBuilder
    {
        return $this->handleJoin($queryBuilder, $alias, $rootAlias);
    }
}
