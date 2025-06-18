<?php

namespace JtcSolutions\Core\Entity;

/**
 * Entities with this interface have history automatically tracked by HistoryListener, and its changes are saved in a database
 */
interface IHistoryTrackable extends IEntity
{
    /**
     * Returns FQCN of the entity that manages history for Entity that implements this interface.
     *
     * @return class-string
     */
    public function getHistoryEntityFQCN(): string;
}