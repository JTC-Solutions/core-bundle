<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Service;

use Doctrine\ORM\EntityNotFoundException as DoctrineEntityNotFoundException;
use JtcSolutions\Core\Dto\EntityId;
use JtcSolutions\Core\Dto\IEntityRequestBody;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Exception\EntityAlreadyExistsException;
use JtcSolutions\Core\Exception\EntityNotFoundException;
use JtcSolutions\Core\Exception\NestedEntityNotFoundException;
use JtcSolutions\Core\Repository\IEntityRepository;
use JtcSolutions\Helpers\Helper\BatchUpdater;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @template TEntity of IEntity
 * @template TRequestBody of IEntityRequestBody
 * @template TRepository of IEntityRepository<TEntity>
 * @implements IEntityService<TEntity, TRequestBody>
 */
abstract class BaseEntityService implements IEntityService
{
    /**
     * @param TRepository $repository
     */
    public function __construct(
        protected readonly IEntityRepository $repository,
        protected readonly LoggerInterface $logger,
    ) {
    }

    /** INTERFACE FOR CONTROLLERS */

    /**
     * @param TRequestBody $requestBody
     * @return TEntity
     */
    public function handleCreate(IEntityRequestBody $requestBody): IEntity
    {
        return $this->mapDataAndCallCreate($requestBody);
    }

    /**
     * @param TEntity|UuidInterface $entityId
     * @param TRequestBody $requestBody
     * @return TEntity
     */
    public function handleUpdate(UuidInterface|IEntity $entityId, IEntityRequestBody $requestBody): IEntity
    {
        return $this->mapDataAndCallUpdate($entityId, $requestBody);
    }

    public function handleDelete(UuidInterface|IEntity $entityId): void
    {
        $this->delete($entityId);
    }

    /** HELPER FUNCTIONS FOR SERVICES */

    /**
     * @param array<string, mixed> $params
     * @throws EntityAlreadyExistsException
     */
    protected function ensureEntityDoesNotExist(array $params, ?UuidInterface $ignoredEntityId = null): void
    {
        try {
            $entity = $this->repository->findOneBy($params);
            if ($ignoredEntityId !== null) {
                if ($entity->getId()->equals($ignoredEntityId)) {
                    return;
                }
            }
            throw EntityAlreadyExistsException::create($this->repository->getEntityName(), $params, $ignoredEntityId);
        } catch (DoctrineEntityNotFoundException $e) {
            return;
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return TEntity
     * @throws DoctrineEntityNotFoundException
     */
    protected function ensureEntityExists(array $params): IEntity
    {
        try {
            /** @var TEntity $entity */
            $entity = $this->repository->findOneBy($params);

            return $entity;
        } catch (DoctrineEntityNotFoundException $e) {
            throw EntityNotFoundException::searchedForByParams($this->repository->getEntityName(), $params, $e);
        }
    }

    /**
     * @param TRepository $repository
     * @return TEntity
     * @throws NestedEntityNotFoundException
     */
    protected function findEntityById(
        UuidInterface $id,
        IEntityRepository $repository,
    ): IEntity {
        $entityName = $repository->getEntityName();

        try {
            return $repository->find($id);
        } catch (DoctrineEntityNotFoundException $e) {
            throw NestedEntityNotFoundException::create($id, $entityName, $this->repository->getEntityName(), $e);
        }
    }

    /**
     * @param IEntity[] $entities
     * @param EntityId[]|IEntity[] $inputs
     */
    protected function updateCollection(
        array $entities,
        array $inputs,
        callable $addEntity,
        callable $removeEntity,
    ): void {
        /** @var array<string, IEntity> $entityMap */
        $entityMap = [];
        foreach ($entities as $entity) {
            $entityMap[$entity->getId()->toString()] = $entity;
        }

        $batchUpdater = new BatchUpdater(
            entities: $entities,
            entityIdGetter: static fn (IEntity $entity) => $entity->getId()->toString(),
            inputs: $inputs,
            inputIdGetter: static fn (EntityId|IEntity $value) => $value instanceof EntityId ? $value->id->toString() : $value->getId()->toString(),
        );

        foreach ($batchUpdater->getIdsToRemove() as $idToRemove) {
            $entity = $entityMap[$idToRemove];
            $removeEntity($entity);
        }

        foreach ($batchUpdater->getIdsToBeCreated() as $idToCreate) {
            $entityId = Uuid::fromString($idToCreate);
            $addEntity($entityId);
        }
    }

    /**
     * REQUIRED FUNCTIONS FOR SERVICES
     */
    abstract protected function delete(UuidInterface|IEntity $id): void;

    /**
     * @param TRequestBody $requestBody
     * @return TEntity
     */
    abstract protected function mapDataAndCallCreate(IEntityRequestBody $requestBody): IEntity;

    /**
     * @param TEntity|UuidInterface $entityId
     * @param TRequestBody $requestBody
     * @return TEntity
     */
    abstract protected function mapDataAndCallUpdate(UuidInterface|IEntity $entityId, IEntityRequestBody $requestBody): IEntity;
}
