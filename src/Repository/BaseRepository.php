<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use JtcSolutions\Core\Entity\IEntity;
use Ramsey\Uuid\UuidInterface;

/**
 * Provides a base implementation for common repository operations using Doctrine ORM.
 * It simplifies standard tasks like finding entities by ID, finding all entities,
 * pagination, counting, and finding by specific criteria.
 * Designed to be extended by concrete repositories for specific entity types.
 *
 * @template TEntity of IEntity The type of the entity managed by this repository.
 */
abstract class BaseRepository
{
    /**
     * The underlying Doctrine EntityRepository instance configured for TEntity.
     * @var EntityRepository<TEntity>
     */
    private EntityRepository $repository;

    /**
     * Initializes the repository by obtaining the specific Doctrine EntityRepository
     * for the given entity class name from the EntityManager.
     *
     * @param EntityManagerInterface $entityManager The Doctrine EntityManager.
     * @param class-string<TEntity> $className The fully qualified class name of the entity (TEntity) this repository manages.
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
     * Finds an entity by its primary identifier (UUID).
     *
     * @param UuidInterface $id The UUID of the entity to find.
     * @return TEntity The found entity instance.
     * @throws EntityNotFoundException If no entity is found for the given ID.
     *         Note: Consider using a custom, potentially translatable exception here if needed.
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
     * Returns the fully qualified class name of the entity managed by this repository.
     *
     * @return class-string<TEntity>
     */
    public function getEntityName(): string
    {
        return $this->className;
    }

    /**
     * Retrieves all entities of the managed type.
     * Use with caution on large tables. Consider pagination or specific criteria instead.
     *
     * @return array<int, TEntity> An array of all entity instances.
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
     * Retrieves a paginated list of entities.
     * Fetches a slice of entities based on offset and limit, and also calculates the total count.
     *
     * @param int<0, max> $offset The number of entities to skip (starting from 0).
     * @param int<1, max> $limit The maximum number of entities to return.
     * @return array{results: TEntity[], total: int<0, max>} An array containing the list of entities for the current page ('results')
     *                                                and the total number of entities available ('total').
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

    /**
     * Counts all entities of the managed type.
     *
     * @return int<0, max> The total number of entities.
     * @throws NonUniqueResultException If the count query somehow returns non-unique result (highly unlikely).
     * @throws NoResultException If the count query returns no result (also unlikely for COUNT).
     */
    public function countAll(): int
    {
        /** @var int<0, max> $count */
        $count = $this->createQueryBuilder('entity')
            ->select('COUNT(entity.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * Finds a single entity that matches the given criteria.
     * Expects exactly one result.
     *
     * @param array<string, mixed> $criteria An array of field => value pairs to match. Handles NULL values correctly.
     * @param array<string, 'ASC'|'DESC'>|null $orderBy Optional ordering criteria (e.g., ['createdAt' => 'DESC']). Keys are field names, values are 'ASC' or 'DESC'.
     * @return TEntity The single entity matching the criteria.
     * @throws EntityNotFoundException If no entity matches the criteria.
     * @throws NonUniqueResultException If more than one entity matches the criteria.
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

    /**
     * Gets a QueryBuilder instance for the managed entity, pre-aliased.
     * Use this as a starting point for more complex custom queries.
     * It internally calls `createQueryBuilder`.
     *
     * @param string $alias The alias to use for the root entity in the query (defaults to 'e').
     * @return QueryBuilder A QueryBuilder instance for the managed entity.
     */
    public function getQueryBuilder(string $alias = 'e'): QueryBuilder
    {
        return $this->createQueryBuilder($alias);
    }

    /**
     * Creates a QueryBuilder instance for the managed entity.
     * This method serves as the primary factory for QueryBuilders within the repository.
     *
     * Subclasses can override this method to add default conditions (e.g., filtering soft-deleted items)
     * to *all* queries created through `getQueryBuilder` or internal methods like `find`, `findAll`, etc.
     * Any direct creation of QueryBuilder outside this method should be justified.
     *
     * @param string $alias The alias to use for the root entity in the query (defaults to 'e').
     * @return QueryBuilder A new QueryBuilder instance.
     */
    protected function createQueryBuilder(string $alias = 'e'): QueryBuilder
    {
        return $this->repository->createQueryBuilder("{$alias}");
    }

    /**
     * TODO: Support other types of joins
     *
     * Helper method to safely add an INNER JOIN to a QueryBuilder.
     * Checks if an alias for the join already exists to prevent duplication errors.
     *
     * @param QueryBuilder $queryBuilder The QueryBuilder instance to modify.
     * @param string $alias The desired alias for the joined entity (e.g., 'related').
     * @param string $rootAlias The alias of the entity from which the join originates (e.g., 'e').
     * @return QueryBuilder The modified QueryBuilder instance.
     */
    protected function handleJoin(
        QueryBuilder $queryBuilder,
        string $alias,
        string $rootAlias,
    ): QueryBuilder {
        // if alias already exists, just reuse it and do not duplicate it
        foreach ($queryBuilder->getAllAliases() as $existingAlias) {
            if ($existingAlias === $alias) {
                return $queryBuilder;
            }
        }

        return $queryBuilder->innerJoin("{$rootAlias}.{$alias}", $alias);
    }
}
