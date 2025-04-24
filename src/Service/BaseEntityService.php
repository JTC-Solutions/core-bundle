<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Service;

use Doctrine\ORM\EntityNotFoundException as DoctrineEntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use JtcSolutions\Core\Dto\EntityId;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Exception\EntityAlreadyExistsException;
use JtcSolutions\Core\Exception\EntityNotFoundException;
use JtcSolutions\Core\Exception\NestedEntityNotFoundException;
use JtcSolutions\Core\Repository\IEntityRepository;
use JtcSolutions\Helpers\Helper\BatchUpdater;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @template TEntity of IEntity The concrete entity type managed by the service subclass.
 * @template TRepository of IEntityRepository<TEntity> The concrete repository type for the managed entity.
 */
abstract class BaseEntityService
{
    protected LoggerInterface $logger;

    /**
     * Constructor for dependency injection.
     *
     * @param TRepository $repository The specific repository instance for the entity (`TEntity`) being managed.
     *                                 Typed as IEntityRepository for constructor compatibility but usually a concrete type via DI.
     */
    public function __construct(
        protected readonly IEntityRepository $repository,
    ) {
    }

    #[Required]
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /** Helper Methods for Concrete Services */

    /**
     * Checks if an entity matching the given criteria already exists in the repository.
     * Optionally ignores a specific entity ID (useful during updates to allow saving the same unique value).
     * Throws an exception if a conflicting entity is found.
     *
     * Example Usage: Call this within `mapDataAndCallCreate` or `mapDataAndCallUpdate` before persisting
     *                to enforce uniqueness constraints (e.g., unique email or name).
     *
     * ```php
     * // In create:
     * $this->ensureEntityDoesNotExist(['email' => $requestBody->email]);
     *
     * // In update:
     * $entity = $this->findEntityById($entityId, $this->repository); // Assuming findEntityById is used
     * $this->ensureEntityDoesNotExist(['email' => $requestBody->email], $entity->getId());
     * ```
     *
     * @param array<string, int|string|bool|null> $params Key-value pairs representing the criteria to check (e.g., ['name' => 'uniqueName']).
     * @param UuidInterface|null $ignoredEntityId If provided, an existing entity with this ID will not trigger the exception.
     * @throws EntityAlreadyExistsException If an entity matching `$params` exists and its ID is not `$ignoredEntityId`.
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
     * Ensures that an entity matching the given criteria exists and returns it.
     * Throws a specific EntityNotFoundException if no matching entity is found.
     *
     * Example Usage: Use this to find a required related entity based on criteria other than ID
     *                during create or update operations.
     *
     * ```php
     * // In create:
     * $relatedCategory = $this->ensureEntityExists(['slug' => $requestBody->categorySlug]);
     * $newProduct->setCategory($relatedCategory);
     * ```
     *
     * @param array<string, int|string|bool|null|UuidInterface> $params Key-value pairs representing the criteria to search for.
     * @return TEntity The found entity.
     * @throws EntityNotFoundException If no entity matching the criteria is found. Wraps Doctrine's exception.
     * @throws NonUniqueResultException If findOneBy finds more than one result (indicates data inconsistency or wrong criteria).
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
     * Finds a related entity by its UUID using a *provided* repository.
     * This is crucial when a service needs to load an entity managed by a *different* repository/service.
     * Throws a specific `NestedEntityNotFoundException` to clearly indicate that a *dependency* was missing.
     *
     * Example Usage: In `ProductService::mapDataAndCallCreate`, find the `Category` entity using the `CategoryRepository`.
     *
     * ```php
     * // In ProductService (assuming categoryRepository is injected or located)
     * $category = $this->findEntityById($requestBody->categoryId, $this->categoryRepository);
     * $newProduct->setCategory($category);
     * ```
     *
     * @template TNestedEntity of IEntity
     * @param UuidInterface $id The UUID of the related entity to find.
     * @param IEntityRepository<TNestedEntity> $repository The repository responsible for the *related* entity type.
     * @return TNestedEntity The found related entity.
     * @throws NestedEntityNotFoundException If the entity with the given ID is not found in the specified repository.
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
     * Helper utility to synchronize a collection of related entities (e.g., ManyToMany or OneToMany).
     * Compares the current collection (`$entities`) with a list of desired inputs (`$inputs`)
     * and calls the provided add/remove callbacks for items that need to be added or removed.
     * Leverages `jtc-solutions/helpers` BatchUpdater for efficient comparison.
     *
     * Example Usage: Update the 'tags' associated with a 'post'.
     *
     * ```php
     * // In PostService::update
     * $post = $this->repository->find($entityId); // Assuming $entityId is UuidInterface
     * $currentTags = $post->getTags()->toArray(); // Get current related entities
     * $inputTagIds = array_map(fn($tagInput) => new EntityId($tagInput->id), $requestBody->tags); // Convert input DTOs/IDs to EntityId[]
     *
     * $this->updateCollection(
     *     entities: $currentTags,
     *     inputs: $inputTagIds,
     *     addEntity: function (UuidInterface $tagIdToAdd) use ($post) {
     *         $tag = $this->findEntityById($tagIdToAdd, $this->tagRepository); // Find the tag to add
     *         $post->addTag($tag); // Call the entity's add method
     *     },
     *     removeEntity: function (IEntity $tagToRemove) use ($post) {
     *         // Ensure $tagToRemove is the correct type if needed (e.g., instanceof Tag)
     *         $post->removeTag($tagToRemove); // Call the entity's remove method
     *     }
     * );
     * ```
     *
     * @param IEntity[] $entities The current collection of related entities associated with the main entity.
     * @param array<int, EntityId|IEntity> $inputs An array representing the desired state of the collection.
     *                                           Each element should be either an `EntityId` DTO (containing just the UUID)
     *                                           or an actual `IEntity` instance.
     * @param callable(UuidInterface): void $addEntity A callback function executed for each entity that needs to be added.
     *                                                It receives the `UuidInterface` of the entity to add.
     *                                                The callback implementation is responsible for finding the entity by this ID
     *                                                and associating it with the main entity (e.g., `$mainEntity->addRelated($foundEntity)`).
     * @param callable(IEntity): void $removeEntity A callback function executed for each entity that needs to be removed.
     *                                             It receives the actual `IEntity` instance from the current collection that should be removed.
     *                                             The callback implementation is responsible for dissociating the entity
     *                                             (e.g., `$mainEntity->removeRelated($entityToRemove)`).
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
}
