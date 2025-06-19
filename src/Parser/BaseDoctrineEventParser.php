<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Parser;

use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Entity\ILabelable;
use JtcSolutions\Core\Enum\HistoryActionTypeEnum;
use JtcSolutions\Helpers\Helper\FQCNHelper;

/**
 * Base parser for extracting change data from Doctrine lifecycle events.
 * Converts Doctrine change sets into structured arrays for history tracking.
 */
abstract class BaseDoctrineEventParser
{
    /**
     * @param string[] $ignoredFields Field names to exclude from change tracking
     */
    public function __construct(
        protected array $ignoredFields = [],
    ) {
    }

    /**
     * Parses Doctrine preUpdate changeSet into structured change entries.
     * Handles scalar values, entity relations, enums, and skips unchanged or ignored fields.
     *
     * @param array<non-empty-string, array{mixed, mixed}|PersistentCollection<int, IEntity>> $changeSet
     * @return array<int, array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     actionType: HistoryActionTypeEnum,
     *     relatedEntity?: string|null,
     *     enumName?: non-empty-string
     * }> Array of change entries
     */
    public function parsePreUpdateArgs(array $changeSet): array
    {
        $changes = [];

        foreach ($changeSet as $field => $change) {
            /** @var array{0: mixed, 1: mixed} $values */
            $values = $change;
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
                /** @var IEntity|null $oldValue */
                /** @var IEntity|null $newValue */
                $changes[] = $this->parseEntityRelationChange($field, $oldValue, $newValue);
                continue;
            }

            if (isset($this->getDefinedEnums()[$field])) {
                /** @var string|int|float|bool|null $newValue */
                /** @var string|int|float|bool|null $oldValue */
                $changes[] = $this->parseEnumChange($field, $newValue, $oldValue);
                continue;
            }

            /** @var DateTimeInterface|string|int|float|bool|null $newValue */
            /** @var DateTimeInterface|string|int|float|bool|null $oldValue */
            $changes[] = $this->parseScalarChange($field, $newValue, $oldValue);
        }

        return $changes;
    }

    /**
     * Parses collection changes from Doctrine's scheduled collection updates.
     * Detects additions and removals from entity collections defined in getDefinedCollections().
     *
     * @param array<int, PersistentCollection<array-key, object>> $scheduledCollectionUpdates
     * @return array<int, array{
     *     field: non-empty-string,
     *     oldValue: array{id: non-empty-string, label: string|null}|null,
     *     newValue: array{id: non-empty-string, label: string|null}|null,
     *     relatedEntity: non-empty-string,
     *     actionType: HistoryActionTypeEnum
     * }> Array of collection changes
     */
    public function parsePreUpdateScheduledCollectionUpdates(
        IHistoryTrackable $historyTrackableEntity,
        array $scheduledCollectionUpdates,
    ): array {
        $changes = [];

        foreach ($this->getDefinedCollections($historyTrackableEntity) as $field => $collection) {
            assert($field !== '');
            foreach ($scheduledCollectionUpdates as $scheduledCollectionUpdate) {
                // TODO: Maybe we want to handle this ?
                if ($scheduledCollectionUpdate->getOwner() !== $historyTrackableEntity) {
                    continue;
                }

                // if the update is not for the same field
                if ($scheduledCollectionUpdate->getMapping()['fieldName'] !== $field) {
                    continue;
                }

                // TODO: maybe check for scalar is useless
                $addedToCollection = array_filter($scheduledCollectionUpdate->getInsertDiff(), static fn ($item) => is_object($item));
                $removedFromCollection = array_filter($scheduledCollectionUpdate->getDeleteDiff(), static fn ($item) => is_object($item));

                foreach ($removedFromCollection as $itemRemovedFromCollection) {
                    /** @var IEntity $itemRemovedFromCollection */
                    $changes[] = $this->parseCollectionChange(
                        $field,
                        $itemRemovedFromCollection,
                        null,
                    );
                }

                foreach ($addedToCollection as $itemAddedToCollection) {
                    /** @var IEntity $itemAddedToCollection */
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

    /**
     * Extracts entity reference data for history storage.
     * Returns an array with UUID string and label (if the entity implements ILabelable).
     *
     * @return array{
     *     id: non-empty-string,
     *     label: string|null
     * }|null Array with 'id' and 'label' keys, or null if the entity is null
     */
    public function parseEntityReference(IEntity|null $entity): ?array
    {
        if (! $entity instanceof IEntity) {
            return null;
        }

        $label = null;

        if ($entity instanceof ILabelable) {
            $label = $entity->getLabel();
        }

        return [
            'id' => $entity->getId()->toString(),
            'label' => $label,
        ];
    }

    /**
     * Creates a collection change entry for an entity added to or removed from a collection.
     *
     * @param non-empty-string $field Collection field name
     * @return array{
     *     field: non-empty-string,
     *     oldValue: array{id: non-empty-string, label: string|null}|null,
     *     newValue: array{id: non-empty-string, label: string|null}|null,
     *     relatedEntity: non-empty-string,
     *     actionType: HistoryActionTypeEnum
     * } Change entry with entity reference and action type
     */
    protected function parseCollectionChange(
        string $field,
        IEntity|null $oldValue,
        IEntity|null $newValue,
    ): array {
        if ($oldValue !== null) {
            $relatedEntity = FQCNHelper::transformFQCNToShortClassName($oldValue::class);
            assert($relatedEntity !== '');
            return [
                'field' => $field,
                'oldValue' => $this->parseEntityReference($oldValue),
                'newValue' => null,
                'relatedEntity' => $relatedEntity,
                'actionType' => HistoryActionTypeEnum::REMOVED_FROM_COLLECTION,
            ];
        }

        /** @var IEntity $newValue PHPStan assertion */
        $relatedEntity = FQCNHelper::transformFQCNToShortClassName($newValue::class);
        assert($relatedEntity !== '');

        return [
            'field' => $field,
            'oldValue' => null,
            'newValue' => $this->parseEntityReference($newValue),
            'relatedEntity' => $relatedEntity,
            'actionType' => HistoryActionTypeEnum::ADDED_TO_COLLECTION,
        ];
    }

    /**
     * Creates a change entry for scalar field modifications.
     * Formats DateTime objects and converts all values to strings for storage.
     *
     * @param non-empty-string $field Field name that changed
     * @return array{
     *     field: non-empty-string,
     *     oldValue: string|null,
     *     newValue: string|null,
     *     relatedEntity: null,
     *     actionType: HistoryActionTypeEnum
     * } Change entry with formatted old/new values
     */
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

    /**
     * Creates a change entry for enum field modifications.
     * Stores raw enum values without transformation.
     *
     * @param non-empty-string $field Enum field name
     * @return array{
     *     field: non-empty-string,
     *     oldValue: string|int|float|bool|null,
     *     newValue: string|int|float|bool|null,
     *     enumName: non-empty-string,
     *     actionType: HistoryActionTypeEnum
     * } Change entry with raw enum values
     */
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

    /**
     * Creates a change entry for entity relation modifications.
     * Extracts entity references with ID and label for both old and new values.
     *
     * @param non-empty-string $field Relation field name
     * @return array{
     *     field: non-empty-string,
     *     oldValue: array{id: non-empty-string, label: string|null}|null,
     *     newValue: array{id: non-empty-string, label: string|null}|null,
     *     relatedEntity: string|null,
     *     actionType: HistoryActionTypeEnum
     * } Change entry with entity references and related entity class
     */
    protected function parseEntityRelationChange(
        string $field,
        IEntity|null $oldValue,
        IEntity|null $newValue,
    ): array {
        $relatedEntity = null;
        if ($newValue instanceof IEntity) {
            $relatedEntity = FQCNHelper::transformFQCNToShortClassName($newValue::class);
        } elseif ($oldValue instanceof IEntity) {
            $relatedEntity = FQCNHelper::transformFQCNToShortClassName($oldValue::class);
        }

        return [
            'field' => $field,
            'oldValue' => $this->parseEntityReference($oldValue),
            'newValue' => $this->parseEntityReference($newValue),
            'relatedEntity' => $relatedEntity,
            'actionType' => HistoryActionTypeEnum::CHANGE_RELATION,
        ];
    }

    /**
     * Converts scalar values to string representation for history storage.
     * Formats DateTime as 'd.m.Y H:i:s', converts numbers and booleans to strings.
     *
     * Example: DateTime -> "15.06.2024 14:30:00", bool true -> "1"
     */
    protected function parseScalarValue(
        DateTimeInterface|string|int|float|bool|null $value,
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

    /**
     * Compares values for equality, handling DateTime objects specially.
     * Uses timestamp comparison for DateTime to avoid timezone issues.
     */
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

    /**
     * Checks if any value in the array is an entity instance.
     * Used to detect entity relation changes in changeSet.
     *
     * @param array<int, mixed> $values
     */
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
     * Returns entity collections that should be tracked for history.
     * Must be implemented by concrete parsers to define which collections to monitor.
     *
     * Example: ['tags' => $entity->getTags(), 'categories' => $entity->getCategories()]
     *
     * @return array<string, Collection<int, IEntity>> Map of field names to collections
     */
    abstract protected function getDefinedCollections(IHistoryTrackable $entity): array;

    /**
     * Returns enum fields that should be tracked for history.
     * Must be implemented by concrete parsers to define which enum fields to monitor.
     *
     * Example: ['status' => StatusEnum::class, 'priority' => PriorityEnum::class]
     *
     * @return array<string, class-string> Map of field names to enum class names
     */
    abstract protected function getDefinedEnums(): array;
}
