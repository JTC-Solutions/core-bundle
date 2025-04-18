<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use JtcSolutions\Core\Entity\IEntity;
use Ramsey\Uuid\UuidInterface;

/** @template TEntity of IEntity */
abstract class BaseRepository
{
    /**
     * @var EntityRepository<TEntity>
     */
    private EntityRepository $repository;

    /**
     * @param class-string $className
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        protected readonly string $className,
    ) {
        /** @var EntityRepository<TEntity> $repository */
        $repository = $entityManager->getRepository($this->className);

        $this->repository = $repository;
    }

    /**
     * @return TEntity
     * @throws EntityNotFoundException
     */
    public function find(UuidInterface $id): IEntity
    {
        try {
            /** @var TEntity $result */
            $result = $this->createQueryBuilder('entity')
                ->andWhere('entity.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getSingleResult();

            return $result;
        } catch (NoResultException $e) {
            throw new EntityNotFoundException(sprintf('Entity not found in %s by find() with id %s', $this->getEntityName(), $id->toString()));
        }
    }

    /**
     * @return class-string
     */
    public function getEntityName(): string
    {
        return $this->className;
    }

    /**
     * @return array<int, TEntity>
     */
    public function findAll(): array
    {
        /** @var array<int, TEntity> $result */
        $result = $this->createQueryBuilder('entity')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return array{results: TEntity[], total: int}
     */
    public function findPaginated(
        int $offset,
        int $limit,
    ): array {
        $queryBuilder = $this->createQueryBuilder('entity');

        // Query for paginated results
        $query = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery();

        /** @var TEntity[] $results */
        $results = $query->getResult();

        // Query for the total count of entities
        $totalCount = $this->countAll();

        return [
            'results' => $results,
            'total' => $totalCount,
        ];
    }

    public function countAll(): int
    {
        /** @var int $count */
        $count = $this->createQueryBuilder('entity')
            ->select('COUNT(entity.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     * @return TEntity
     */
    public function findOneBy(array $criteria, array|null $orderBy = null): IEntity
    {
        $qb = $this->createQueryBuilder('entity');

        foreach ($criteria as $field => $value) {
            if ($value === null) {
                $qb->andWhere("entity.{$field} IS NULL");
                continue;
            }

            $qb->andWhere("entity.{$field} = :{$field}")
                ->setParameter($field, $value);
        }

        if ($orderBy !== null) {
            foreach ($orderBy as $field => $direction) {
                $qb->addOrderBy($field, $direction);
            }
        }

        try {
            /** @var TEntity $result */
            $result = $qb->getQuery()->getSingleResult();

            return $result;
        } catch (NoResultException $e) {
            throw new EntityNotFoundException(sprintf('Entity not found in %s by findOneBy method', $this->getEntityName()));
        }
    }

    public function getQueryBuilder(string $alias = 'e'): QueryBuilder
    {
        return $this->createQueryBuilder($alias);
    }

    /**
     * For cases when we want to apply some conditions by default
     * we can override this method and add those filters.
     * This method (or override) should be used for most queries,
     * all exceptions where you are creating query builder yourself
     * should be explained in comment.
     */
    protected function createQueryBuilder(string $alias = 'e'): QueryBuilder
    {
        /**
         * @psalm-suppress UndefinedThisPropertyFetch
         */
        return $this->repository->createQueryBuilder("{$alias}");
    }

    protected function handleJoin(QueryBuilder $queryBuilder, string $alias, string $rootAlias): QueryBuilder
    {
        // if alias already exists, just reuse it and do not duplicate it
        foreach ($queryBuilder->getAllAliases() as $existingAlias) {
            if ($existingAlias === $alias) {
                return $queryBuilder;
            }
        }

        return $queryBuilder->innerJoin("{$rootAlias}.{$alias}", $alias);
    }
}
