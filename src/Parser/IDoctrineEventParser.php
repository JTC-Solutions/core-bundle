<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Parser;

use Doctrine\ORM\PersistentCollection;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;

interface IDoctrineEventParser
{
    /**
     * @param array<int, PersistentCollection<array-key, object>> $scheduledCollectionUpdates
     * @return array<int, array{
     *     field: non-empty-string,
     *     oldValue: array{id: non-empty-string, label: string|null}|null,
     *     newValue: array{id: non-empty-string, label: string|null}|null,
     *     relatedEntity: non-empty-string,
     *     actionType: \JtcSolutions\Core\Enum\HistoryActionTypeEnum
     * }>
     */
    public function parsePreUpdateScheduledCollectionUpdates(
        IHistoryTrackable $historyTrackableEntity,
        array $scheduledCollectionUpdates,
    ): array;

    /**
     * @param array<string, array{mixed, mixed}|PersistentCollection<int, IEntity>> $changeSet
     * @return array<int, array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     actionType: \JtcSolutions\Core\Enum\HistoryActionTypeEnum,
     *     relatedEntity?: string|null,
     *     enumName?: non-empty-string
     * }>
     */
    public function parsePreUpdateArgs(array $changeSet): array;
}
