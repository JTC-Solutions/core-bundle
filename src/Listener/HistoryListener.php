<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Listener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Entity\IPivotHistoryTrackable;
use JtcSolutions\Core\Enum\HistoryActionTypeEnum;
use JtcSolutions\Core\Exception\HistoryException;
use JtcSolutions\Core\Extractor\BaseChangeExtractor;
use JtcSolutions\Core\Factory\BaseHistoryFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

/**
 * Doctrine event listener that automatically tracks changes to entities implementing IHistoryTrackable.
 * Orchestrates the history tracking process by using extractors to parse changes
 * and factories to create history entries.
 */
class HistoryListener
{
    /**
     * @param BaseHistoryFactory[] $historyFactories
     * @param BaseChangeExtractor[] $changeExtractors
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        #[AutowireIterator('app.change_extractor')]
        private readonly iterable $changeExtractors,
        #[AutowireIterator('app.history_factory')]
        private readonly iterable $historyFactories,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Handles entity creation events.
     * Creates a history entry documenting the entity creation.
     * Also handles pivot entity creation for entities implementing IPivotHistoryTrackable.
     *
     * @throws HistoryException
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $startTime = microtime(true);
        $entity = $args->getObject();
        $entityClass = $entity::class;

        $this->logger->debug('History tracking: postPersist event triggered', [
            'entity_class' => $entityClass,
            'entity_id' => method_exists($entity, 'getId') && $entity instanceof IEntity ? $entity->getId()->toString() : 'unknown',
        ]);

        try {
            // Handle pivot entity creation
            if ($entity instanceof IPivotHistoryTrackable) {
                $this->logger->info('History tracking: Processing pivot entity creation', [
                    'entity_class' => $entityClass,
                    'pivot_entity' => true,
                ]);
                $this->handlePivotEntityChange($entity, HistoryActionTypeEnum::PIVOT_CREATED);

                $duration = (microtime(true) - $startTime) * 1000;
                $this->logger->debug('History tracking: Pivot entity creation completed', [
                    'entity_class' => $entityClass,
                    'duration_ms' => round($duration, 2),
                ]);
                return;
            }

            if (! $entity instanceof IHistoryTrackable) {
                $this->logger->debug('History tracking: Entity does not implement IHistoryTrackable, skipping', [
                    'entity_class' => $entityClass,
                ]);
                return;
            }

            /** @var UserInterface|null $user */
            $user = $this->security->getUser();
            $userId = $user?->getUserIdentifier() ?? 'anonymous';

            $this->logger->info('History tracking: Processing entity creation', [
                'entity_class' => $entityClass,
                'user_id' => $userId,
            ]);

            $changeExtractor = $this->getChangeExtractor($entity);
            $changes = $changeExtractor->extractCreationData($entity);

            $this->logger->debug('History tracking: Creation data extracted', [
                'entity_class' => $entityClass,
                'changes' => $changes,
            ]);

            $this->getHistoryFactory($entity)->createFromCreate($user, $entity, $changes);

            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->info('History tracking: Entity creation tracking completed', [
                'entity_class' => $entityClass,
                'duration_ms' => round($duration, 2),
            ]);
        } catch (HistoryException $e) {
            $this->logger->error('History tracking: Failed to track entity creation', [
                'entity_class' => $entityClass,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            // Don't re-throw - history tracking should not break normal application flow
        } catch (Throwable $e) {
            $this->logger->error('History tracking: Unexpected error during entity creation tracking', [
                'entity_class' => $entityClass,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            // Don't re-throw - history tracking should not break normal application flow
        }
    }

    /**
     * Handles entity update events (pre-update phase).
     * Extracts all types of changes and creates history entries.
     * Also handles pivot entity updates for entities implementing IPivotHistoryTrackable.
     *
     * @throws HistoryException
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $startTime = microtime(true);
        $entity = $args->getObject();
        $entityClass = $entity::class;

        $this->logger->debug('History tracking: preUpdate event triggered', [
            'entity_class' => $entityClass,
            'entity_id' => method_exists($entity, 'getId') && $entity instanceof IEntity ? $entity->getId()->toString() : 'unknown',
        ]);

        try {
            // Handle pivot entity updates
            if ($entity instanceof IPivotHistoryTrackable) {
                /** @var array<non-empty-string, array{mixed, mixed}> $changeSet */
                $changeSet = $args->getEntityChangeSet();

                $this->logger->info('History tracking: Processing pivot entity update', [
                    'entity_class' => $entityClass,
                    'pivot_entity' => true,
                    'change_count' => count($changeSet),
                ]);

                $this->handlePivotEntityChange($entity, HistoryActionTypeEnum::PIVOT_UPDATED, $changeSet);

                $duration = (microtime(true) - $startTime) * 1000;
                $this->logger->debug('History tracking: Pivot entity update completed', [
                    'entity_class' => $entityClass,
                    'duration_ms' => round($duration, 2),
                ]);
                return;
            }

            if (! $entity instanceof IHistoryTrackable) {
                $this->logger->debug('History tracking: Entity does not implement IHistoryTrackable, skipping', [
                    'entity_class' => $entityClass,
                ]);
                return;
            }

            $unitOfWork = $this->entityManager->getUnitOfWork();
            $changeExtractor = $this->getChangeExtractor($entity);

            /** @var array<non-empty-string, array{mixed, mixed}> $changeSet */
            $changeSet = $args->getEntityChangeSet();

            /** @var UserInterface|null $user */
            $user = $this->security->getUser();
            $userId = $user?->getUserIdentifier() ?? 'anonymous';

            $this->logger->info('History tracking: Processing entity update', [
                'entity_class' => $entityClass,
                'user_id' => $userId,
                'field_change_count' => count($changeSet),
            ]);

            $extractStartTime = microtime(true);
            $changes = $changeExtractor->extractUpdateDataWithEntity($entity, $changeSet);
            $collectionChanges = $changeExtractor->extractCollectionUpdateData($entity, $unitOfWork->getScheduledCollectionUpdates());
            $collectionDeletions = $changeExtractor->extractCollectionDeleteData($entity, $unitOfWork->getScheduledCollectionDeletions());
            $extractDuration = (microtime(true) - $extractStartTime) * 1000;

            $allChanges = array_merge($changes, $collectionChanges, $collectionDeletions);

            $this->logger->debug('History tracking: Change extraction completed', [
                'entity_class' => $entityClass,
                'field_changes' => count($changes),
                'collection_changes' => count($collectionChanges),
                'collection_deletions' => count($collectionDeletions),
                'total_changes' => count($allChanges),
                'extraction_duration_ms' => round($extractDuration, 2),
            ]);

            // if no changes, just skip
            if ($allChanges === []) {
                $this->logger->debug('History tracking: No changes detected, skipping history creation', [
                    'entity_class' => $entityClass,
                ]);
                return;
            }

            $this->getHistoryFactory($entity)->createFromUpdate($user, $entity, $allChanges);

            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->info('History tracking: Entity update tracking completed', [
                'entity_class' => $entityClass,
                'total_changes' => count($allChanges),
                'duration_ms' => round($duration, 2),
            ]);
        } catch (HistoryException $e) {
            $this->logger->error('History tracking: Failed to track entity update', [
                'entity_class' => $entityClass,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            // Don't re-throw - history tracking should not break normal application flow
        } catch (Throwable $e) {
            $this->logger->error('History tracking: Unexpected error during entity update tracking', [
                'entity_class' => $entityClass,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            // Don't re-throw - history tracking should not break normal application flow
        }
    }

    /**
     * Handles post-update events.
     * Flushes any pending history entries to the database.
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->entityManager->flush();
    }

    /**
     * Handles entity removal events (pre-remove phase).
     * Creates a history entry documenting pivot entity deletion.
     *
     * @throws HistoryException
     */
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $startTime = microtime(true);
        $entity = $args->getObject();
        $entityClass = $entity::class;

        $this->logger->debug('History tracking: preRemove event triggered', [
            'entity_class' => $entityClass,
            'entity_id' => method_exists($entity, 'getId') && $entity instanceof IEntity ? $entity->getId()->toString() : 'unknown',
        ]);

        try {
            // Handle pivot entity deletion
            if ($entity instanceof IPivotHistoryTrackable) {
                $this->logger->info('History tracking: Processing pivot entity deletion', [
                    'entity_class' => $entityClass,
                    'pivot_entity' => true,
                ]);

                $this->handlePivotEntityChange($entity, HistoryActionTypeEnum::PIVOT_DELETED);

                $duration = (microtime(true) - $startTime) * 1000;
                $this->logger->debug('History tracking: Pivot entity deletion completed', [
                    'entity_class' => $entityClass,
                    'duration_ms' => round($duration, 2),
                ]);
                return;
            }

            // Regular entity removal tracking could be added here in the future
            $this->logger->debug('History tracking: Regular entity removal tracking not implemented', [
                'entity_class' => $entityClass,
            ]);
        } catch (HistoryException $e) {
            $this->logger->error('History tracking: Failed to track entity removal', [
                'entity_class' => $entityClass,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            // Don't re-throw - history tracking should not break normal application flow
        } catch (Throwable $e) {
            $this->logger->error('History tracking: Unexpected error during entity removal tracking', [
                'entity_class' => $entityClass,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            // Don't re-throw - history tracking should not break normal application flow
        }
    }

    /**
     * Finds the appropriate history factory for the given entity.
     *
     * @throws HistoryException When no factory supports the entity
     */
    protected function getHistoryFactory(IHistoryTrackable $entity): BaseHistoryFactory
    {
        $entityClass = $entity::class;

        $this->logger->debug('History tracking: Looking for history factory', [
            'entity_class' => $entityClass,
        ]);

        foreach ($this->historyFactories as $historyFactory) {
            if ($historyFactory->supports($entity)) {
                $this->logger->debug('History tracking: Found history factory', [
                    'entity_class' => $entityClass,
                    'factory_class' => $historyFactory::class,
                ]);
                return $historyFactory;
            }
        }

        $this->logger->error('History tracking: No history factory found', [
            'entity_class' => $entityClass,
            'available_factories' => array_map(static fn ($factory) => $factory::class, iterator_to_array($this->historyFactories)),
        ]);

        throw HistoryException::factoryNotFound($entityClass);
    }

    /**
     * Finds the appropriate change extractor for the given entity.
     *
     * @throws HistoryException When no extractor supports the entity
     */
    protected function getChangeExtractor(IHistoryTrackable $entity): BaseChangeExtractor
    {
        $entityClass = $entity::class;

        $this->logger->debug('History tracking: Looking for change extractor', [
            'entity_class' => $entityClass,
        ]);

        foreach ($this->changeExtractors as $changeExtractor) {
            if ($changeExtractor->supports($entity)) {
                $this->logger->debug('History tracking: Found change extractor', [
                    'entity_class' => $entityClass,
                    'extractor_class' => $changeExtractor::class,
                    'parser_class' => $changeExtractor->parser::class,
                ]);
                return $changeExtractor;
            }
        }

        $this->logger->error('History tracking: No change extractor found', [
            'entity_class' => $entityClass,
            'available_extractors' => array_map(static fn ($extractor) => $extractor::class, iterator_to_array($this->changeExtractors)),
        ]);

        throw HistoryException::changeExtractorNotFound($entityClass);
    }

    /**
     * Handles pivot entity lifecycle changes by creating history entries
     * in the context of the pivot's owner entity.
     *
     * @param array<non-empty-string, array{mixed, mixed}>|null $changeSet
     * @throws HistoryException
     */
    private function handlePivotEntityChange(
        IPivotHistoryTrackable $pivotEntity,
        HistoryActionTypeEnum $actionType,
        ?array $changeSet = null,
    ): void {
        $pivotEntityClass = $pivotEntity::class;
        $owner = $pivotEntity->getHistoryOwner();
        $target = $pivotEntity->getHistoryTarget();
        $ownerClass = $owner::class;
        $targetClass = $target::class;

        /** @var UserInterface|null $user */
        $user = $this->security->getUser();
        $userId = $user?->getUserIdentifier() ?? 'anonymous';

        $this->logger->debug('History tracking: Handling pivot entity change', [
            'pivot_entity_class' => $pivotEntityClass,
            'owner_entity_class' => $ownerClass,
            'target_entity_class' => $targetClass,
            'action_type' => $actionType->value,
            'user_id' => $userId,
            'has_change_set' => $changeSet !== null,
            'change_set_fields' => $changeSet !== null ? array_keys($changeSet) : [],
        ]);

        // Create history for the owner entity (from owner's perspective)
        try {
            $ownerChangeExtractor = $this->getChangeExtractor($owner);
            $ownerPivotChange = $ownerChangeExtractor->parser->parsePivotEntityChange(
                $pivotEntity,
                $actionType,
                $changeSet,
            );

            $this->logger->debug('History tracking: Owner pivot change parsed', [
                'pivot_entity_class' => $pivotEntityClass,
                'owner_entity_class' => $ownerClass,
                'change_field' => $ownerPivotChange['field'],
                'change_action' => $ownerPivotChange['actionType']->value,
            ]);

            $this->getHistoryFactory($owner)->createFromUpdate($user, $owner, [$ownerPivotChange]);

            $this->logger->debug('History tracking: Owner pivot history created', [
                'pivot_entity_class' => $pivotEntityClass,
                'owner_entity_class' => $ownerClass,
            ]);
        } catch (HistoryException $e) {
            $this->logger->warning('History tracking: Failed to create owner pivot history', [
                'pivot_entity_class' => $pivotEntityClass,
                'owner_entity_class' => $ownerClass,
                'error' => $e->getMessage(),
            ]);
        }

        // Create history for the target entity (from target's perspective) if it's trackable
        if ($target instanceof IHistoryTrackable) {
            try {
                $targetChangeExtractor = $this->getChangeExtractor($target);
                $targetPivotChange = $targetChangeExtractor->parser->parsePivotEntityChangeForTarget(
                    $pivotEntity,
                    $actionType,
                    $changeSet,
                );

                $this->logger->debug('History tracking: Target pivot change parsed', [
                    'pivot_entity_class' => $pivotEntityClass,
                    'target_entity_class' => $targetClass,
                    'change_field' => $targetPivotChange['field'],
                    'change_action' => $targetPivotChange['actionType']->value,
                ]);

                $this->getHistoryFactory($target)->createFromUpdate($user, $target, [$targetPivotChange]);

                $this->logger->debug('History tracking: Target pivot history created', [
                    'pivot_entity_class' => $pivotEntityClass,
                    'target_entity_class' => $targetClass,
                ]);
            } catch (HistoryException $e) {
                $this->logger->warning('History tracking: Failed to create target pivot history', [
                    'pivot_entity_class' => $pivotEntityClass,
                    'target_entity_class' => $targetClass,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            $this->logger->debug('History tracking: Target entity is not history trackable, skipping target history', [
                'pivot_entity_class' => $pivotEntityClass,
                'target_entity_class' => $targetClass,
            ]);
        }

        $this->logger->debug('History tracking: Pivot entity change completed', [
            'pivot_entity_class' => $pivotEntityClass,
            'owner_entity_class' => $ownerClass,
            'target_entity_class' => $targetClass,
            'target_is_trackable' => $target instanceof IHistoryTrackable,
        ]);
    }
}
