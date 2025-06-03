<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Service;

use Exception;
use JtcSolutions\Core\Dto\IEntityRequestBody;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Exception\EntityAlreadyExistsException;
use JtcSolutions\Core\Exception\EntityNotFoundException;
use JtcSolutions\Core\Exception\NestedEntityNotFoundException;
use Ramsey\Uuid\UuidInterface;

/**
 * Defines the core contract for services responsible for managing the lifecycle of a specific entity type.
 *
 * This interface represents the primary Application Service layer for entity manipulation.
 * It acts as the main entry point for controllers or other application-level services
 * that need to perform Create, Update, or Delete (CUD) operations on entities.
 *
 * The use of generics allows implementations to be strongly typed for the specific
 * entity (`TEntity`) and the corresponding Data Transfer Object (`TRequestBody`) used
 * for carrying request data, ensuring type safety and clarity throughout the application layer.
 *
 * Implementations of this interface encapsulate the business logic, validation rules (often orchestrated),
 * and interaction with the persistence layer (via repositories) related to a particular entity.
 *
 * @template TEntity of IEntity The specific entity type managed by the service implementation.
 * @template TRequestBody of IEntityRequestBody The specific DTO type used to transfer data for creating/updating the entity.
 */
interface IEntityService
{
    /**
     * Handles the creation of a new entity based on the provided request data.
     *
     * This method is expected to:
     * 1. Validate the incoming data (potentially delegating to validation services or using DTO validation).
     * 2. Map the validated data from the DTO (`TRequestBody`) to a new entity instance (`TEntity`).
     * 3. Perform any necessary business logic or checks (e.g., uniqueness constraints).
     * 4. Persist the new entity using the appropriate repository.
     * 5. Return the newly created and persisted entity.
     *
     * @param TRequestBody $requestBody The data transfer object containing the necessary information to create the entity.
     * @param array<string, mixed> $context Additional context data for the operation.
     * @return TEntity The newly created entity instance, typically after being persisted.
     * @throws EntityAlreadyExistsException If an entity with conflicting unique constraints already exists.
     * @throws NestedEntityNotFoundException If a related entity referenced in the request body does not exist.
     * @throws Exception For other potential infrastructure or business logic errors during creation.
     */
    public function handleCreate(IEntityRequestBody $requestBody, array $context = []): IEntity;

    /**
     * Handles the update of an existing entity identified by its UUID, using the provided request data.
     *
     * This method is expected to:
     * 1. Find the existing entity by its ID. If not found, an exception should be thrown.
     * 2. Validate the incoming data (`TRequestBody`).
     * 3. Map the validated data from the DTO onto the existing entity instance.
     * 4. Perform any necessary business logic or checks (e.g., uniqueness constraints, state transitions).
     * 5. Persist the changes to the entity using the appropriate repository.
     * 6. Return the updated and persisted entity.
     *
     * @param UuidInterface|TEntity $entityId Either the UUID of the entity to update or the entity instance itself.
     *                                        Using the instance can sometimes avoid an extra fetch if already loaded.
     * @param TRequestBody $requestBody The data transfer object containing the updated information for the entity.
     * @param array<string, mixed> $context Additional context data for the operation.
     * @return TEntity The updated entity instance, typically after changes have been persisted.
     * @throws EntityNotFoundException If the entity identified by `$entityId` cannot be found.
     * @throws EntityAlreadyExistsException If the update would violate unique constraints (conflicting with another entity).
     * @throws NestedEntityNotFoundException If a related entity referenced in the request body does not exist.
     * @throws Exception For other potential infrastructure or business logic errors during update.
     */
    public function handleUpdate(UuidInterface|IEntity $entityId, IEntityRequestBody $requestBody, array $context = []): IEntity;

    /**
     * Handles the deletion of an existing entity identified by its UUID or instance.
     *
     * This method is expected to:
     * 1. Find the entity if an ID is provided. If not found, an exception might be thrown, or the operation might silently succeed depending on idempotency requirements.
     * 2. Perform any necessary pre-deletion checks or business logic (e.g., checking for dependencies, ensuring deletable state).
     * 3. Remove the entity from the persistence layer (either hard delete or soft delete, depending on implementation).
     *
     * @param TEntity|UuidInterface $entityId Either the UUID of the entity to delete or the entity instance itself.
     * @param array<string, mixed> $context Additional context data for the operation.
     * @throws EntityNotFoundException If deletion requires the entity to exist and it cannot be found by the provided UUID.
     * @throws Exception For other potential infrastructure or business logic errors during deletion.
     */
    public function handleDelete(UuidInterface|IEntity $entityId, array $context = []): void;
}
