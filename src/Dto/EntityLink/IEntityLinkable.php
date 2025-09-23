<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto\EntityLink;

/**
 * Simple url link can be generated from this interface.
 */
interface IEntityLinkable
{
    /**
     * @return class-string
     */
    public function getSubjectFQCN(): string;

    /**
     * @return non-empty-string
     */
    public function getSubjectId(): string;
}
