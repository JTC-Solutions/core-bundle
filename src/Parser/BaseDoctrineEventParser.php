<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Parser;

use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use InvalidArgumentException;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Entity\ILabelable;
use JtcSolutions\Core\Entity\IPivotHistoryTrackable;
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
     * Parses pivot entity changes into history format.
     * Extracts both the target entity reference and additional pivot data.
     *
     * @param array<non-empty-string, array{mixed, mixed}>|null $changeSet Doctrine changeSet for updates, null for create/delete
     * @return array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     relatedEntity: non-empty-string,
     *     actionType: HistoryActionTypeEnum,
     *     pivotEntity: non-empty-string
     * }
     */
    public function parsePivotEntityChange(
        IPivotHistoryTrackable $pivotEntity,
        HistoryActionTypeEnum $actionType,
        ?array $changeSet = null,
    ): array {
        $relationshipType = $pivotEntity->getRelationshipType();
        $targetEntity = $pivotEntity->getHistoryTarget();
        $pivotData = $pivotEntity->getPivotData();

        $targetReference = $this->parseEntityReference($targetEntity);
        if ($targetReference !== null && $pivotData !== []) {
            $targetReference['pivotData'] = $pivotData;
        }

        $relatedEntityType = FQCNHelper::transformFQCNToShortClassName($targetEntity::class);
        assert($relatedEntityType !== '');

        $pivotEntityType = FQCNHelper::transformFQCNToShortClassName($pivotEntity::class);
        assert($pivotEntityType !== '');

        return match ($actionType) {
            HistoryActionTypeEnum::PIVOT_CREATED => [
                'field' => $relationshipType,
                'oldValue' => null,
                'newValue' => $targetReference,
                'relatedEntity' => $relatedEntityType,
                'actionType' => $actionType,
                'pivotEntity' => $pivotEntityType,
            ],
            HistoryActionTypeEnum::PIVOT_DELETED => [
                'field' => $relationshipType,
                'oldValue' => $targetReference,
                'newValue' => null,
                'relatedEntity' => $relatedEntityType,
                'actionType' => $actionType,
                'pivotEntity' => $pivotEntityType,
            ],
            HistoryActionTypeEnum::PIVOT_UPDATED => $this->parsePivotUpdateChange(
                $pivotEntity,
                $changeSet ?? [],
                $relationshipType,
                $relatedEntityType,
                $pivotEntityType,
            ),
            default => throw new InvalidArgumentException('Invalid action type for pivot entity: ' . $actionType->value),
        };
    }

    /**
     * Parses pivot entity changes from the target entity's perspective.
     * This creates the "reverse" history entry for the other side of the relationship.
     *
     * For example, if User gets Role:
     * - Owner perspective: "User got role Admin"
     * - Target perspective: "User John was added among admins"
     *
     * @param array<non-empty-string, array{mixed, mixed}>|null $changeSet
     * @return array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     relatedEntity: non-empty-string,
     *     actionType: HistoryActionTypeEnum,
     *     pivotEntity: non-empty-string
     * }
     */
    public function parsePivotEntityChangeForTarget(
        IPivotHistoryTrackable $pivotEntity,
        HistoryActionTypeEnum $actionType,
        ?array $changeSet = null,
    ): array {
        $relationshipType = $pivotEntity->getRelationshipType();
        $ownerEntity = $pivotEntity->getHistoryOwner();
        $pivotData = $pivotEntity->getPivotData();

        $ownerReference = $this->parseEntityReference($ownerEntity);
        if ($ownerReference !== null && $pivotData !== []) {
            $ownerReference['pivotData'] = $pivotData;
        }

        $relatedEntityType = FQCNHelper::transformFQCNToShortClassName($ownerEntity::class);
        assert($relatedEntityType !== '');

        $pivotEntityType = FQCNHelper::transformFQCNToShortClassName($pivotEntity::class);
        assert($pivotEntityType !== '');

        // Create reverse relationship field names based on the original relationship type
        $reverseRelationshipType = $this->inferReverseRelationshipType($relationshipType);

        return match ($actionType) {
            HistoryActionTypeEnum::PIVOT_CREATED => [
                'field' => $reverseRelationshipType,
                'oldValue' => null,
                'newValue' => $ownerReference,
                'relatedEntity' => $relatedEntityType,
                'actionType' => $actionType,
                'pivotEntity' => $pivotEntityType,
            ],
            HistoryActionTypeEnum::PIVOT_DELETED => [
                'field' => $reverseRelationshipType,
                'oldValue' => $ownerReference,
                'newValue' => null,
                'relatedEntity' => $relatedEntityType,
                'actionType' => $actionType,
                'pivotEntity' => $pivotEntityType,
            ],
            HistoryActionTypeEnum::PIVOT_UPDATED => $this->parsePivotUpdateChangeForTarget(
                $pivotEntity,
                $changeSet ?? [],
                $reverseRelationshipType,
                $relatedEntityType,
                $pivotEntityType,
            ),
            default => throw new InvalidArgumentException('Invalid action type for pivot entity: ' . $actionType->value),
        };
    }

    /**
     * Parses pivot entity reference data including additional pivot data.
     * Similar to parseEntityReference but includes pivot-specific information.
     *
     * @return array{
     *     id: non-empty-string,
     *     label: string|null,
     *     pivotData?: array<string, mixed>
     * }|null
     */
    public function parsePivotEntityReference(IPivotHistoryTrackable $pivotEntity): ?array
    {
        $targetEntity = $pivotEntity->getHistoryTarget();
        $baseReference = $this->parseEntityReference($targetEntity);

        if ($baseReference === null) {
            return null;
        }

        $pivotData = $pivotEntity->getPivotData();
        if ($pivotData !== []) {
            $baseReference['pivotData'] = $pivotData;
        }

        return $baseReference;
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

    /**
     * Returns pivot entity class names that should be tracked for this entity.
     * Must be implemented by concrete parsers to define which pivot entities to monitor.
     *
     * Only pivot entities implementing IPivotHistoryTrackable will be tracked.
     * The relationship name should match the field used in history entries.
     *
     * Example: ['roles' => UserRole::class, 'projects' => UserProject::class]
     *
     * @return array<string, class-string<IPivotHistoryTrackable>> Map of relationship names to pivot entity classes
     */
    abstract protected function getDefinedPivotEntities(IHistoryTrackable $entity): array;

    /**
     * Handles pivot entity update changes by comparing old and new pivot data.
     *
     * @param array<non-empty-string, array{mixed, mixed}> $changeSet
     * @param non-empty-string $relationshipType
     * @param non-empty-string $relatedEntityType
     * @param non-empty-string $pivotEntityType
     * @return array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     relatedEntity: non-empty-string,
     *     actionType: HistoryActionTypeEnum,
     *     pivotEntity: non-empty-string
     * }
     */
    private function parsePivotUpdateChange(
        IPivotHistoryTrackable $pivotEntity,
        array $changeSet,
        string $relationshipType,
        string $relatedEntityType,
        string $pivotEntityType,
    ): array {
        // For pivot updates, we focus on the changed pivot data fields
        // The target entity reference typically doesn't change in updates
        $targetReference = $this->parseEntityReference($pivotEntity->getHistoryTarget());
        $pivotData = $pivotEntity->getPivotData();

        // Extract the specific field that changed from the changeSet
        $changedFields = [];
        foreach ($changeSet as $field => [$oldValue, $newValue]) {
            if (! $this->areValuesTheSame($oldValue, $newValue)) {
                $changedFields[$field] = [
                    'from' => $this->parseScalarValue($oldValue instanceof DateTimeInterface || is_scalar($oldValue) || $oldValue === null ? $oldValue : 'unknown'),
                    'to' => $this->parseScalarValue($newValue instanceof DateTimeInterface || is_scalar($newValue) || $newValue === null ? $newValue : 'unknown'),
                ];
            }
        }

        $newValue = $targetReference;
        if ($newValue !== null && $pivotData !== []) {
            $newValue['pivotData'] = $pivotData;
        }

        return [
            'field' => $relationshipType,
            'oldValue' => $changedFields,
            'newValue' => $newValue,
            'relatedEntity' => $relatedEntityType,
            'actionType' => HistoryActionTypeEnum::PIVOT_UPDATED,
            'pivotEntity' => $pivotEntityType,
        ];
    }

    /**
     * Parses pivot entity update changes from the target entity's perspective.
     * Similar to parsePivotUpdateChange but with swapped perspective.
     *
     * @param array<non-empty-string, array{mixed, mixed}> $changeSet
     * @return array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     relatedEntity: non-empty-string,
     *     actionType: HistoryActionTypeEnum,
     *     pivotEntity: non-empty-string
     * }
     */
    private function parsePivotUpdateChangeForTarget(
        IPivotHistoryTrackable $pivotEntity,
        array $changeSet,
        string $relationshipType,
        string $relatedEntityType,
        string $pivotEntityType,
    ): array {
        assert($relationshipType !== '');
        assert($relatedEntityType !== '');
        assert($pivotEntityType !== '');

        // For target perspective, we show what changed in the pivot data
        // but frame it from the target's viewpoint
        $changedFields = [];

        foreach ($changeSet as $field => $changes) {
            [$oldValue, $currentNewValue] = $changes;
            $changedFields[$field] = [
                'old' => $oldValue,
                'new' => $currentNewValue,
            ];
        }

        $ownerEntity = $pivotEntity->getHistoryOwner();
        $ownerReference = $this->parseEntityReference($ownerEntity);

        return [
            'field' => $relationshipType,
            'oldValue' => $changedFields,
            'newValue' => $ownerReference, // Reference to the owner entity from target's perspective
            'relatedEntity' => $relatedEntityType,
            'actionType' => HistoryActionTypeEnum::PIVOT_UPDATED,
            'pivotEntity' => $pivotEntityType,
        ];
    }

    /**
     * Infers the reverse relationship type name for target entity perspective.
     *
     * Examples:
     * - "role" -> "user" (user gets role, role gets user)
     * - "permission" -> "user"
     * - "tag" -> "item"
     *
     * @param non-empty-string $relationshipType
     * @return non-empty-string
     */
    private function inferReverseRelationshipType(string $relationshipType): string
    {
        // Common relationship type mappings
        $mappings = [
            'role' => 'user',
            'permission' => 'user',
            'user' => 'role',
            'tag' => 'item',
            'item' => 'tag',
            'group' => 'member',
            'member' => 'group',
            'category' => 'item',
        ];

        // Check if we have a predefined mapping
        if (isset($mappings[$relationshipType])) {
            return $mappings[$relationshipType];
        }

        // Try to infer from common patterns
        if (str_ends_with($relationshipType, 's')) {
            // "roles" -> "user", "permissions" -> "user"
            return 'user';
        }

        // Default fallback - just add "entity" suffix
        return $relationshipType . '_entity';
    }
}
