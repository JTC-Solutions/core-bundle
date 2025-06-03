<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Service;

use Exception;
use JtcSolutions\Core\Dto\IEntityRequestBody;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Exception\EntityNotFoundException;
use JtcSolutions\Core\Repository\IEntityRepository;
use Ramsey\Uuid\UuidInterface;

/**
 * Provides a foundational abstract implementation of the IEntityService interface.
 *
 * This class is designed to be extended by concrete entity services (e.g., `UserService`, `ProductService`).
 * It significantly reduces boilerplate code by:
 * 1. Implementing the public `handle*` methods using the Template Method pattern, delegating the core
 *    data mapping and persistence logic to abstract methods (`mapDataAndCallCreate`, `mapDataAndCallUpdate`, `delete`).
 * 2. Providing common helper methods for frequent tasks like existence checks (`ensureEntityExists`,
 *    `ensureEntityDoesNotExist`), finding related entities (`findEntityById`), and managing collections (`updateCollection`).
 * 3. Injecting and providing access to the entity's specific repository (`TRepository`) and a logger.
 *
 * Concrete services extending this base class primarily need to implement the abstract methods,
 * focusing on the specific mapping logic and domain rules for their particular entity.
 *
 * @template TEntity of IEntity The concrete entity type managed by the service subclass.
 * @template TRequestBody of IEntityRequestBody The concrete DTO type used for the entity's create/update operations.
 * @template TRepository of IEntityRepository<TEntity> The concrete repository type for the managed entity.
 * @extends BaseEntityService<TEntity, TRepository>
 * @implements IEntityService<TEntity, TRequestBody> Indicates that this class (and its subclasses) fulfill the IEntityService contract.
 */
abstract class BaseCRUDEntityService extends BaseEntityService implements IEntityService
{
    /**
     * @param TRepository $repository
     */
    public function __construct(IEntityRepository $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Public handler for creating an entity.
     * Implements the Template Method pattern: calls the abstract `mapDataAndCallCreate` method
     * which must be implemented by concrete subclasses.
     *
     * @param TRequestBody $requestBody The DTO containing data for the new entity.
     * @param array<string, mixed> $context Additional context data for the operation.
     * @return TEntity The newly created and persisted entity.
     * @see IEntityService::handleCreate() for expected exceptions and behavior.
     * @see BaseCRUDEntityService::mapDataAndCallCreate() for the required implementation logic.
     */
    public function handleCreate(IEntityRequestBody $requestBody, array $context = []): IEntity
    {
        return $this->mapDataAndCallCreate($requestBody, $context);
    }

    /**
     * Public handler for updating an existing entity.
     * Implements the Template Method pattern: calls the abstract `mapDataAndCallUpdate` method
     * which must be implemented by concrete subclasses.
     *
     * @param TEntity|UuidInterface $entityId Either the entity instance or its UUID.
     * @param TRequestBody $requestBody The DTO containing the updated data.
     * @param array<string, mixed> $context Additional context data for the operation.
     * @return TEntity The updated and persisted entity.
     * @see IEntityService::handleUpdate() for expected exceptions and behavior.
     * @see BaseCRUDEntityService::mapDataAndCallUpdate() for the required implementation logic.
     */
    public function handleUpdate(UuidInterface|IEntity $entityId, IEntityRequestBody $requestBody, array $context = []): IEntity
    {
        return $this->mapDataAndCallUpdate($entityId, $requestBody, $context);
    }

    /**
     * Public handler for deleting an entity.
     * Implements the Template Method pattern: calls the abstract `delete` method
     * which must be implemented by concrete subclasses.
     *
     * @param TEntity|UuidInterface $entityId Either the entity instance or its UUID.
     * @param array<string, mixed> $context Additional context data for the operation.
     * @see IEntityService::handleDelete() for expected exceptions and behavior.
     * @see BaseCRUDEntityService::delete() for the required implementation logic.
     */
    public function handleDelete(UuidInterface|IEntity $entityId, array $context = []): void
    {
        $this->delete($entityId, $context);
    }

    /**
     * Abstract method defining the actual deletion logic.
     * Concrete implementations must handle finding the entity (if `$id` is a UUID),
     * performing necessary checks, and executing the delete operation (hard or soft).
     *
     * @param TEntity|UuidInterface $id The entity instance or UUID to delete.
     * @param array<string, mixed> $context Additional context data for the operation.
     * @throws EntityNotFoundException If deletion requires the entity to exist and it's not found by UUID.
     * @throws \Exception Implementations might throw exceptions for constraint violations or other errors.
     */
    abstract protected function delete(UuidInterface|IEntity $id, array $context = []): void;

    /**
     * Abstract method defining the bridge between the generic `handleCreate` and the concrete service's creation logic.
     *
     * Concrete implementations typically:
     * 1. Extract specific properties required for creation from the generic `$requestBody` DTO.
     * 2. Perform any necessary pre-creation checks or data transformations (e.g., fetching related entities using `findEntityById`).
     * 3. Call a private/protected method within the concrete service (e.g., a `create` method) that takes these specific
     *    properties, handles entity instantiation, applies business rules (like using `ensureEntityDoesNotExist`),
     *    persists the entity via `$this->repository` or EntityManager, and returns the new `TEntity`.
     *
     * @param TRequestBody $requestBody The input DTO conforming to `IEntityRequestBody`. The implementation will cast or access specific properties from it.
     * @param array<string, mixed> $context Additional context data for the operation.
     * @return TEntity The newly created and persisted entity.
     * @throws Exception Implementations can throw various exceptions (Validation, Persistence, Business Logic related).
     */
    abstract protected function mapDataAndCallCreate(IEntityRequestBody $requestBody, array $context = []): IEntity;

    /**
     * Abstract method defining the bridge between the generic `handleUpdate` and the concrete service's update logic.
     *
     * Concrete implementations typically:
     * 1. Extract specific properties needing update from the generic `$requestBody` DTO.
     * 2. Perform any necessary pre-update checks or data transformations (e.g., fetching related entities, checking uniqueness
     *    using `ensureEntityDoesNotExist` with the ignored ID).
     * 3. Call a private/protected method within the concrete service (e.g., an `update` method) that takes the `$entityId`
     *    (or fetched entity) and the extracted properties. This internal method handles finding the entity (if an ID was passed),
     *    applying the changes, managing relationships (e.g., using `updateCollection`), persisting the changes,
     *    and returning the updated `TEntity`.
     *
     * @param TEntity|UuidInterface $entityId The entity instance or UUID to update.
     * @param TRequestBody $requestBody The input DTO conforming to `IEntityRequestBody`, containing the data for the update.
     * @param array<string, mixed> $context Additional context data for the operation.
     * @return TEntity The updated and persisted entity.
     * @throws EntityNotFoundException If the entity is not found when `$entityId` is a UUID and the internal update logic requires fetching it.
     * @throws Exception Implementations can throw various exceptions (Validation, Persistence, Business Logic related).
     */
    abstract protected function mapDataAndCallUpdate(UuidInterface|IEntity $entityId, IEntityRequestBody $requestBody, array $context = []): IEntity;
}
