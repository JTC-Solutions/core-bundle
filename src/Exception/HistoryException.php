<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Exception;

use JtcSolutions\Core\Entity\IHistoryTrackable;
use LogicException;

class HistoryException extends LogicException
{
    /**
     * @param class-string $entity
     */
    public static function factoryNotFound(string $entity): self
    {
        return new self("Factory for entity {$entity} not found, maybe you forgot to add it to BaseHistoryFactory?");
    }

    /**
     * @param class-string $entity
     */
    public static function changeExtractorNotFound(string $entity): self
    {
        return new self("ChangeExtractor for entity {$entity} not found, maybe you forgot to add it to BaseChangeExtractor?");
    }

    public static function repositoryNotFound(string $entity): self
    {
        return new self("Repository for entity {$entity} not found, maybe you forgot to add it to IHistoryTrackableRepository?");
    }

    /**
     * @param class-string $entityName
     */
    public static function listenerReceivedEntityWithoutEntityInterface(string $entityName): self
    {
        return new self(sprintf('Listener received entity %s without %s interface.', $entityName, IHistoryTrackable::class));
    }

    public static function historyFactoryHasNotDefinedClassNameConstant(string $historyFactoryClassName): self
    {
        return new self("HistoryFactory {$historyFactoryClassName} has not defined CLASS_NAME constant.");
    }
}
