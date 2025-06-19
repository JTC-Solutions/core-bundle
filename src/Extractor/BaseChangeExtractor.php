<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Extractor;

use Doctrine\ORM\PersistentCollection;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Parser\BaseDoctrineEventParser;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Base extractor for handling change extraction from different Doctrine events.
 * Uses a parser to process the actual change data and delegates the parsing logic.
 */
abstract class BaseChangeExtractor
{
    public function __construct(
        public readonly BaseDoctrineEventParser $parser,
        protected readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    abstract public function supports(IHistoryTrackable $entity): bool;

    /**
     * Extracts creation data for a newly persisted entity.
     * Returns entity reference with ID and label for history tracking.
     *
     * @return array{
     *     id: non-empty-string,
     *     label: string|null
     * }
     */
    public function extractCreationData(IHistoryTrackable $entity): array
    {
        $startTime = microtime(true);
        $entityClass = $entity::class;

        $this->logger->debug('Change extractor: Extracting creation data', [
            'entity_class' => $entityClass,
            'extractor_class' => static::class,
            'parser_class' => $this->parser::class,
        ]);

        try {
            $result = $this->parser->parseEntityReference($entity) ?? [
                'id' => $entity->getId()->toString(),
                'label' => null,
            ];

            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->debug('Change extractor: Creation data extracted', [
                'entity_class' => $entityClass,
                'result' => $result,
                'duration_ms' => round($duration, 2),
            ]);

            return $result;
        } catch (Throwable $e) {
            $this->logger->error('Change extractor: Failed to extract creation data', [
                'entity_class' => $entityClass,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Extracts update data from Doctrine changeSet.
     *
     * @param array<non-empty-string, array{mixed, mixed}|PersistentCollection<int, \JtcSolutions\Core\Entity\IEntity>> $changeSet
     * @return array<int, array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     actionType: \JtcSolutions\Core\Enum\HistoryActionTypeEnum,
     *     relatedEntity?: string|null,
     *     enumName?: non-empty-string
     * }>
     */
    public function extractUpdateData(array $changeSet): array
    {
        $startTime = microtime(true);

        $this->logger->debug('Change extractor: Extracting update data', [
            'extractor_class' => static::class,
            'parser_class' => $this->parser::class,
            'field_count' => count($changeSet),
            'fields' => array_keys($changeSet),
        ]);

        try {
            $result = $this->parser->parsePreUpdateArgs($changeSet);

            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->debug('Change extractor: Update data extracted', [
                'field_count' => count($changeSet),
                'change_count' => count($result),
                'duration_ms' => round($duration, 2),
            ]);

            return $result;
        } catch (Throwable $e) {
            $this->logger->error('Change extractor: Failed to extract update data', [
                'field_count' => count($changeSet),
                'fields' => array_keys($changeSet),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Enhanced version that provides entity context for better enum/collection detection.
     * Use this method when using MetadataAwareDoctrineEventParser for automatic detection.
     *
     * @param array<non-empty-string, array{mixed, mixed}|PersistentCollection<int, \JtcSolutions\Core\Entity\IEntity>> $changeSet
     * @return array<int, array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     actionType: \JtcSolutions\Core\Enum\HistoryActionTypeEnum,
     *     relatedEntity?: string|null,
     *     enumName?: non-empty-string
     * }>
     */
    public function extractUpdateDataWithEntity(IHistoryTrackable $entity, array $changeSet): array
    {
        $startTime = microtime(true);
        $entityClass = $entity::class;

        $this->logger->debug('Change extractor: Extracting update data with entity context', [
            'entity_class' => $entityClass,
            'extractor_class' => static::class,
            'parser_class' => $this->parser::class,
            'field_count' => count($changeSet),
            'supports_entity_context' => method_exists($this->parser, 'parsePreUpdateArgsWithEntity'),
        ]);

        try {
            // Check if the parser supports entity-aware parsing
            if (method_exists($this->parser, 'parsePreUpdateArgsWithEntity')) {
                /** @var \JtcSolutions\Core\Parser\MetadataAwareDoctrineEventParser $parser */
                $parser = $this->parser;
                $result = $parser->parsePreUpdateArgsWithEntity($entity, $changeSet);

                $this->logger->debug('Change extractor: Used entity-aware parsing', [
                    'entity_class' => $entityClass,
                    'field_count' => count($changeSet),
                    'change_count' => count($result),
                ]);
            } else {
                // Fallback to regular parsing
                $result = $this->extractUpdateData($changeSet);

                $this->logger->debug('Change extractor: Used fallback parsing (no entity context)', [
                    'entity_class' => $entityClass,
                    'field_count' => count($changeSet),
                    'change_count' => count($result),
                ]);
            }

            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->debug('Change extractor: Update data with entity context extracted', [
                'entity_class' => $entityClass,
                'change_count' => count($result),
                'duration_ms' => round($duration, 2),
            ]);

            return $result;
        } catch (Throwable $e) {
            $this->logger->error('Change extractor: Failed to extract update data with entity context', [
                'entity_class' => $entityClass,
                'field_count' => count($changeSet),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Extracts collection update data from scheduled collection updates.
     *
     * @param array<int, PersistentCollection<array-key, object>> $scheduledCollectionUpdates
     * @return array<int, array{
     *     field: non-empty-string,
     *     oldValue: array{id: non-empty-string, label: string|null}|null,
     *     newValue: array{id: non-empty-string, label: string|null}|null,
     *     relatedEntity: non-empty-string,
     *     actionType: \JtcSolutions\Core\Enum\HistoryActionTypeEnum
     * }>
     */
    public function extractCollectionUpdateData(
        IHistoryTrackable $entity,
        array $scheduledCollectionUpdates,
    ): array {
        $startTime = microtime(true);
        $entityClass = $entity::class;

        $this->logger->debug('Change extractor: Extracting collection update data', [
            'entity_class' => $entityClass,
            'extractor_class' => static::class,
            'collection_update_count' => count($scheduledCollectionUpdates),
        ]);

        try {
            $result = $this->parser->parsePreUpdateScheduledCollectionUpdates($entity, $scheduledCollectionUpdates);

            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->debug('Change extractor: Collection update data extracted', [
                'entity_class' => $entityClass,
                'collection_update_count' => count($scheduledCollectionUpdates),
                'change_count' => count($result),
                'duration_ms' => round($duration, 2),
            ]);

            return $result;
        } catch (Throwable $e) {
            $this->logger->error('Change extractor: Failed to extract collection update data', [
                'entity_class' => $entityClass,
                'collection_update_count' => count($scheduledCollectionUpdates),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Extracts collection deletion data from scheduled collection deletions.
     * Currently returns empty array - implement in concrete classes if needed.
     *
     * @param array<int, PersistentCollection<array-key, object>> $scheduledCollectionDeletions
     * @return array<int, array{
     *     field: non-empty-string,
     *     oldValue: array{id: non-empty-string, label: string|null}|null,
     *     newValue: array{id: non-empty-string, label: string|null}|null,
     *     relatedEntity: non-empty-string,
     *     actionType: \JtcSolutions\Core\Enum\HistoryActionTypeEnum
     * }>
     */
    public function extractCollectionDeleteData(
        IHistoryTrackable $entity,
        array $scheduledCollectionDeletions,
    ): array {
        // Default implementation - override in concrete classes if needed
        return [];
    }

    /**
     * Extracts removal data for a deleted entity.
     * Returns entity reference with ID and label for history tracking.
     *
     * @return array{
     *     id: non-empty-string,
     *     label: string|null
     * }
     */
    public function extractRemoveData(IHistoryTrackable $entity): array
    {
        return $this->parser->parseEntityReference($entity) ?? [
            'id' => $entity->getId()->toString(),
            'label' => null,
        ];
    }
}
