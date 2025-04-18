<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Repository;

use Doctrine\ORM\QueryBuilder;
use JtcSolutions\Core\Entity\IEntity;
use Ramsey\Uuid\UuidInterface;

/** @template TEntity of IEntity */
interface IEntityRepository
{
    /**
     * @return TEntity
     */
    public function find(UuidInterface $id): IEntity;

    /**
     * @return TEntity[]
     */
    public function findAll(): array;

    /**
     * @return array{results: TEntity[], total: int}
     */
    public function findPaginated(int $offset, int $limit): array;

    public function countAll(): int;

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     *
     * @return TEntity
     */
    public function findOneBy(array $criteria, array|null $orderBy = null): IEntity;

    public function getQueryBuilder(string $alias = 'e'): QueryBuilder;

    /**
     * @return class-string
     */
    public function getEntityName(): string;
}
