<?php

namespace JtcSolutions\Core\Parser;

use Doctrine\ORM\PersistentCollection;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;

interface IDoctrineEventParser
{
    /** @param array<int, PersistentCollection<array-key, object>> $scheduledCollectionUpdates */
    public function parsePreUpdateScheduledCollectionUpdates(
        IHistoryTrackable $historyTrackableEntity,
        array $scheduledCollectionUpdates
    ): array;

    /**
     * @param array<string, array{mixed, mixed}|PersistentCollection<int, IEntity>> $changeSet
     */
    public function parsePreUpdateArgs(array $changeSet): array;
}