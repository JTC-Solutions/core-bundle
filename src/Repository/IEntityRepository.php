<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Repository;

use Doctrine\ORM\EntityNotFoundException as DoctrineEntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use JtcSolutions\Core\Entity\IEntity;
use Ramsey\Uuid\UuidInterface;

/**
 * Defines the standard contract for repository classes managing entities implementing IEntity.
 * Ensures basic CRUD-like operations and query building capabilities are available.
 *
 * @template TEntity of IEntity The type of the entity managed by implementations of this repository interface.
 */
interface IEntityRepository
{
    /**
     * Finds an entity by its primary identifier (UUID).
     *
     * @param UuidInterface $id The UUID of the entity to find.
     * @return TEntity The found entity instance.
     * @throws DoctrineEntityNotFoundException If no entity is found for the given ID. Implementations should handle or throw this.
     */
    public function find(UuidInterface $id): IEntity;

    /**
     * Retrieves all entities of the managed type.
     * Implementations should be mindful of performance on large datasets.
     *
     * @return TEntity[] An array of all entity instances. Returns an empty array if none are found.
     */
    public function findAll(): array;

    /**
     * Retrieves a paginated list of entities.
     *
     * @param int<0, max> $offset The number of entities to skip (starting from 0).
     * @param int<1, max> $limit The maximum number of entities to return.
     * @return array{results: TEntity[], total: int<0, max>} An array containing the list of entities for the current page ('results')
     *                                                and the total number of entities available ('total').
     */
    public function findPaginated(int $offset, int $limit): array;

    /**
     * Counts all entities of the managed type.
     * Implementations might apply default filters (like soft-delete) if applicable.
     *
     * @return int<0, max> The total number of entities matching the repository's base criteria.
     */
    public function countAll(): int;

    /**
     * Finds a single entity that matches the given criteria.
     * Expects exactly one result based on the criteria.
     *
     * @param array<string, mixed> $criteria An array of field => value pairs to match.
     * @param array<string, 'ASC'|'DESC'>|null $orderBy Optional ordering criteria (e.g., ['name' => 'ASC']).
     * @return TEntity The single entity matching the criteria.
     * @throws DoctrineEntityNotFoundException If no entity matches the criteria.
     * @throws NonUniqueResultException If more than one entity matches the criteria.
     */
    public function findOneBy(array $criteria, array|null $orderBy = null): IEntity;

    /**
     * Gets a QueryBuilder instance for the managed entity, pre-aliased.
     * Useful for building custom, complex queries.
     *
     * @param string $alias The alias to use for the root entity in the query (defaults to 'e').
     * @return QueryBuilder A QueryBuilder instance.
     */
    public function getQueryBuilder(string $alias = 'e'): QueryBuilder;

    /**
     * Returns the fully qualified class name of the entity managed by this repository.
     *
     * @return class-string<TEntity>
     */
    public function getEntityName(): string;
}
