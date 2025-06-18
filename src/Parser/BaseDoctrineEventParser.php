<?php

namespace JtcSolutions\Core\Parser;

use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Entity\ILabelable;
use JtcSolutions\Core\Enum\HistoryActionTypeEnum;
use JtcSolutions\Helpers\Helper\FQCNHelper;

abstract class BaseDoctrineEventParser
{
    /** @param string[] $ignoredFields Properties which are not part of the result of the Parser */
    public function __construct(
        protected array $ignoredFields = []
    ) {
    }

    /**
     * @param array<string, array{mixed, mixed}|PersistentCollection<int, IEntity>> $changeSet
     */
    public function parsePreUpdateArgs(array $changeSet): array
    {
        $changes = [];

        foreach ($changeSet as $field => $change) {
            /** @var array{0: mixed, 1: mixed} $values */
            [$oldValue, $newValue] = $values;

            // if values are the same, they have not changed and do not include them in changes
            if ($this->areValuesTheSame($oldValue, $newValue) === true) {
                continue;
            }

            // if the field is among ignoredFields, do not include it in the change
            if (in_array($field, $this->ignoredFields, true)) {
                continue;
            }

            if ($this->isAnyValueEntityReference([$oldValue, $newValue])) {
                $changes[] = $this->parseEntityRelationChange($field, $oldValue, $newValue);
                continue;
            }

            if (isset($this->getDefinedEnums()[$field])) {
                $changes[] = $this->parseEnumChange($field, $newValue, $oldValue);
                continue;
            }

            $changes[] = $this->parseScalarChange($field, $newValue, $oldValue);
        }

        return $changes;
    }

    /**
     * // TODO: Im not sure if DefinedCollections is really necessary to call, maybe we can work just with scheduledCollectionUpdates ?
     * @param array<int, PersistentCollection<array-key, object>> $scheduledCollectionUpdates
     */
    public function parsePreUpdateScheduledCollectionUpdates(
        IHistoryTrackable $historyTrackableEntity,
        array $scheduledCollectionUpdates
    ): array {
        $changes = [];

        foreach ($this->getDefinedCollections($historyTrackableEntity) as $field => $collection) {
            foreach ($scheduledCollectionUpdates as $scheduledCollectionUpdate) {
                // TODO: Maybe we want to handle this ?
                if ($scheduledCollectionUpdate->getOwner() !== $historyTrackableEntity) {
                    continue;
                }

                // if the update is not for the same field
                if ($scheduledCollectionUpdate->getMapping()["fieldName"] !== $field) {
                    continue;
                }

                // TODO: maybe check for scalar is useless
                $addedToCollection = array_filter($scheduledCollectionUpdate->getInsertDiff(), static fn ($item) => is_object($item));
                $removedFromCollection = array_filter($scheduledCollectionUpdate->getDeleteDiff(), static fn ($item) => is_object($item));

                foreach ($removedFromCollection as $itemRemovedFromCollection) {
                    $changes[] = $this->parseCollectionChange(
                        $field,
                        $itemRemovedFromCollection,
                        null,
                    );
                }

                foreach ($addedToCollection as $itemAddedToCollection) {
                    $changes[] = $this->parseCollectionChange(
                        $field,
                        null,
                        $itemAddedToCollection,
                    );
                }
            }
        }

        return $changes;
    }

    protected function parseCollectionChange(
        string $field,
        IEntity|null $oldValue,
        IEntity|null $newValue,
    ): array {
        if ($oldValue !== null) {
            return [
                'field' => $field,
                'oldValue' => $this->parseEntityReference($oldValue),
                'newValue' => null,
                'relatedEntity' => FQCNHelper::transformFQCNToShortClassName($oldValue::class),
                'actionType' => HistoryActionTypeEnum::REMOVED_FROM_COLLECTION,
            ];
        }

        return [
            'field' => $field,
            'oldValue' => null,
            'newValue' => $this->parseEntityReference($newValue),
            'relatedEntity' => FQCNHelper::transformFQCNToShortClassName($newValue::class),
            'actionType' => HistoryActionTypeEnum::ADDED_TO_COLLECTION,
        ];
    }

    protected function parseScalarChange(
        string $field,
        DateTimeInterface|string|int|float|bool|null $newValue,
        DateTimeInterface|string|int|float|bool|null $oldValue,
    ): array {
        return [
            'field' => $field,
            'oldValue' => $this->parseScalarValue($oldValue),
            'newValue' => $this->parseScalarValue($newValue),
            'relatedEntity' => null,
            'actionType' => HistoryActionTypeEnum::UPDATE,
        ];
    }

    protected function parseEnumChange(
        string $field,
        string|int|float|bool|null $newValue,
        string|int|float|bool|null $oldValue,
    ): array {
        return [
            'field' => $field,
            'oldValue' => $oldValue,
            'newValue' => $newValue,
            'enumName' => $field,
            'actionType' => HistoryActionTypeEnum::UPDATE,
        ];
    }

    protected function parseEntityRelationChange(
        string $field,
        IEntity|null $oldValue,
        IEntity|null $newValue,
    ): array {
        $relatedEntity = null;
        if ($newValue instanceof IEntity) {
            $relatedEntity = FQCNHelper::transformFQCNToShortClassName($newValue::class);
        } else if ($oldValue instanceof IEntity) {
            $relatedEntity = FQCNHelper::transformFQCNToShortClassName($oldValue::class);
        }

        return [
            "field" => $field,
            "oldValue" => $this->parseEntityReference($oldValue),
            "newValue" => $this->parseEntityReference($newValue),
            "relatedEntity" => $relatedEntity,
            "actionTYpe" => HistoryActionTypeEnum::CHANGE_RELATION
        ];
    }

    protected function parseScalarValue(
        DateTimeInterface|string|int|float|bool|null $value
    ): string|null {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('d.m.Y H:i:s');
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return (string) $value;
        }

        return $value;
    }

    protected function parseEntityReference(IEntity|null $entity): ?array
    {
        if (!$entity instanceof IEntity) {
            return null;
        }

        $label = null;

        if ($entity instanceof ILabelable) {
            $label = $entity->getLabel();
        }

        return [
            "id" => $entity->getId()->toString(),
            "label" => $label
        ];
    }

    protected function areValuesTheSame(mixed $oldValue, mixed $newValue): bool
    {
        // for simple scalar types
        if ($oldValue === $newValue) {
            return true;
        }

        // for datetime compare timestamps
        if ($oldValue instanceof DateTimeInterface && $newValue instanceof DateTimeInterface) {
            return $oldValue->getTimestamp() === $newValue->getTimestamp();
        }

        return false;
    }

    protected function isAnyValueEntityReference(array $values): bool
    {
        foreach ($values as $value) {
            if ($value instanceof IEntity) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, Collection<int, IEntity>>
     */
    abstract protected function getDefinedCollections(IHistoryTrackable $entity): array;

    /**
     * @return array<string, class-string>
     */
    abstract protected function getDefinedEnums(): array;
}