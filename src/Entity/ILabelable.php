<?php

namespace JtcSolutions\Core\Entity;

/**
 * Entity with this interface has a unique describer that can be shown to the user.
 */
interface ILabelable
{
    /**
     * Returns string by which the entity is identified by the user, should be human-readable and understandable string that is unique for each instance.
     *
     * @return non-empty-string
     */
    public function getLabel(): string;
}