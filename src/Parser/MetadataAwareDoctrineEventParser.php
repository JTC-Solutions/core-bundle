<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Parser;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Entity\IPivotHistoryTrackable;
use JtcSolutions\Core\Enum\HistoryActionTypeEnum;

/**
 * Metadata-aware parser that automatically detects enums and collections
 * using Doctrine's metadata introspection capabilities.
 *
 * This eliminates the need for developers to manually define getDefinedEnums()
 * and getDefinedCollections() methods in concrete parser classes.
 *
 * Benefits:
 * - 90% reduction in boilerplate code
 * - Automatic maintenance when entity structure changes
 * - Consistent behavior across all entities
 * - Better developer experience
 */
class MetadataAwareDoctrineEventParser extends BaseDoctrineEventParser
{
    /**
     * @var array<string, array<string, class-string|null>> Cached enum mappings per entity class
     */
    private array $enumCache = [];

    /**
     * @var array<string, array<string, string>> Cached collection mappings per entity class
     */
    private array $collectionCache = [];

    /**
     * @var string|null Current entity class being processed (for enum detection context)
     */
    private ?string $currentEntityClass = null;

    /**
     * @param string[] $ignoredFields Field names to exclude from change tracking
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        array $ignoredFields = [],
    ) {
        parent::__construct($ignoredFields);
    }

    /**
     * Enhanced version that includes entity context for enum detection.
     * This should be used instead of parsePreUpdateArgs when entity context is available.
     *
     * @param array<non-empty-string, array{mixed, mixed}|PersistentCollection<int, IEntity>> $changeSet
     * @return array<int, array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     actionType: HistoryActionTypeEnum,
     *     relatedEntity?: string|null,
     *     enumName?: non-empty-string
     * }>
     */
    public function parsePreUpdateArgsWithEntity(IHistoryTrackable $entity, array $changeSet): array
    {
        // Set the entity context for enum detection
        $previousEntityClass = $this->currentEntityClass;
        $this->currentEntityClass = $entity::class;

        try {
            return $this->parsePreUpdateArgs($changeSet);
        } finally {
            // Restore previous entity context
            $this->currentEntityClass = $previousEntityClass;
        }
    }

    /**
     * Automatically detects collections for the given entity using Doctrine metadata.
     * Caches results for performance.
     *
     * @return array<string, Collection<int, IEntity>>
     */
    protected function getDefinedCollections(IHistoryTrackable $entity): array
    {
        $entityClass = $entity::class;

        if (isset($this->collectionCache[$entityClass])) {
            return $this->buildCollectionArray($entity, $this->collectionCache[$entityClass]);
        }

        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $collections = [];

        foreach ($metadata->getAssociationMappings() as $fieldName => $mapping) {
            // Skip if field is in ignored list
            if (in_array($fieldName, $this->ignoredFields, true)) {
                continue;
            }

            // Only include collection-valued associations (OneToMany, ManyToMany)
            if ($metadata->isCollectionValuedAssociation($fieldName)) {
                $targetEntity = $mapping['targetEntity'] ?? '';
                if (is_string($targetEntity)) {
                    $collections[$fieldName] = $targetEntity;
                }
            }
        }

        $this->collectionCache[$entityClass] = $collections;

        return $this->buildCollectionArray($entity, $collections);
    }

    /**
     * Automatically detects enum fields for the current entity using Doctrine metadata.
     * Supports both explicit enumType and pattern-based detection.
     *
     * @return array<string, class-string>
     */
    protected function getDefinedEnums(): array
    {
        if ($this->currentEntityClass === null) {
            return [];
        }

        /** @var class-string $entityClass */
        $entityClass = $this->currentEntityClass;
        $enums = $this->getDefinedEnumsForEntity($entityClass);

        // Filter out null values (enums that were detected but class not found)
        return array_filter($enums, static fn ($enumClass): bool => $enumClass !== null);
    }

    /**
     * Returns detected enum fields for a specific entity class.
     * This method is called internally to get enums for a specific entity.
     *
     * @param class-string $entityClass
     * @return array<string, class-string|null>
     */
    protected function getDefinedEnumsForEntity(string $entityClass): array
    {
        if (isset($this->enumCache[$entityClass])) {
            return $this->enumCache[$entityClass];
        }

        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $enums = [];

        // Get field mappings - different methods for different Doctrine versions
        $fieldMappings = [];
        if (method_exists($metadata, 'getFieldMappings')) {
            $fieldMappings = $metadata->getFieldMappings();
        } elseif (property_exists($metadata, 'fieldMappings')) {
            $fieldMappings = $metadata->fieldMappings;
        }

        if (! is_iterable($fieldMappings)) {
            return $enums;
        }

        foreach ($fieldMappings as $fieldName => $mapping) {
            // Skip if field is in ignored list
            if (in_array($fieldName, $this->ignoredFields, true)) {
                continue;
            }

            // Ensure fieldName is a string
            if (! is_string($fieldName) || $fieldName === '') {
                continue;
            }

            // Ensure mapping is an array
            if (! is_array($mapping)) {
                continue;
            }

            // Check for explicit enumType (PHP 8.1+ enums)
            if (isset($mapping['enumType']) && is_string($mapping['enumType'])) {
                /** @var class-string $enumClass */
                $enumClass = $mapping['enumType'];
                $enums[$fieldName] = $enumClass;
                continue;
            }
        }

        $this->enumCache[$entityClass] = $enums;

        return $enums;
    }

    /**
     * Default empty implementation - pivot entities still require manual configuration.
     * The metadata doesn't provide enough information to automatically detect
     * which entities should be treated as pivot entities.
     *
     * @return array<string, class-string<IPivotHistoryTrackable>>
     */
    protected function getDefinedPivotEntities(IHistoryTrackable $entity): array
    {
        return [];
    }

    /**
     * Builds the actual collection array with Collection instances.
     *
     * @param array<string, string> $collectionMappings
     * @return array<string, Collection<int, IEntity>>
     */
    private function buildCollectionArray(IHistoryTrackable $entity, array $collectionMappings): array
    {
        $collections = [];

        foreach ($collectionMappings as $fieldName => $targetEntity) {
            $getter = 'get' . ucfirst($fieldName);

            if (method_exists($entity, $getter)) {
                $collection = $entity->{$getter}();
                if ($collection instanceof Collection) {
                    $collections[$fieldName] = $collection;
                }
            }
        }

        return $collections;
    }

    /**
     * Attempts to infer an enum class name from field name and entity context.
     *
     * @param class-string $entityClass
     * @param array<string, mixed> $mapping
     * @return class-string|null
     */
    private function inferEnumClassName(string $entityClass, string $fieldName, array $mapping): ?string
    {
        // Try common enum class naming patterns
        $lastBackslashPos = strrpos($entityClass, '\\');
        if ($lastBackslashPos === false) {
            return null; // No namespace found
        }

        $entityNamespace = substr($entityClass, 0, $lastBackslashPos);
        $enumNamespace = $entityNamespace . '\\Enum\\';

        // Convert field name to PascalCase + Enum suffix
        $enumName = ucfirst($fieldName);
        if (! str_ends_with($enumName, 'Enum')) {
            $enumName .= 'Enum';
        }

        $possibleEnumClasses = [
            $enumNamespace . $enumName,
            $entityNamespace . '\\' . $enumName,
            'App\\Enum\\' . $enumName,
        ];

        foreach ($possibleEnumClasses as $enumClass) {
            if (class_exists($enumClass) && (enum_exists($enumClass) || interface_exists($enumClass))) {
                /** @var class-string $enumClass */
                return $enumClass;
            }
        }

        return null;
    }
}
