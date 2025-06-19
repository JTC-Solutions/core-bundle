<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Extractor;

use Doctrine\ORM\PersistentCollection;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Parser\BaseDoctrineEventParser;

/**
 * Base extractor for handling change extraction from different Doctrine events.
 * Uses a parser to process the actual change data and delegates the parsing logic.
 */
abstract class BaseChangeExtractor
{
    public function __construct(
        protected readonly BaseDoctrineEventParser $parser,
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
        return $this->parser->parseEntityReference($entity) ?? [
            'id' => $entity->getId()->toString(),
            'label' => null,
        ];
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
        return $this->parser->parsePreUpdateArgs($changeSet);
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
        return $this->parser->parsePreUpdateScheduledCollectionUpdates($entity, $scheduledCollectionUpdates);
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
