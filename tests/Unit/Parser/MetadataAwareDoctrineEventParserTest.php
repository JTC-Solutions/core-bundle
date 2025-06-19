<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\Parser;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Enum\HistoryActionTypeEnum;
use JtcSolutions\Core\Parser\MetadataAwareDoctrineEventParser;
use JtcSolutions\Core\Tests\Fixtures\History\TestHistoryTrackableEntity;
use JtcSolutions\Core\Tests\Fixtures\History\TestRelatedEntity;
use JtcSolutions\Core\Tests\Fixtures\History\TestStatusEnum;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for MetadataAwareDoctrineEventParser.
 *
 * This test class validates the automatic detection of enums and collections
 * using Doctrine's metadata introspection capabilities.
 */
class MetadataAwareDoctrineEventParserTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    private MetadataAwareDoctrineEventParser $parser;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->parser = new MetadataAwareDoctrineEventParser($this->entityManager);
    }

    /**
     * Test that enums with explicit enumType annotation are detected.
     */
    public function testDetectsEnumsWithExplicitEnumType(): void
    {
        // Given field mappings with explicit enumType
        $fieldMappings = [
            'status' => [
                'fieldName' => 'status',
                'type' => 'string',
                'enumType' => TestStatusEnum::class,
            ],
            'priority' => [
                'fieldName' => 'priority',
                'type' => 'string',
                'enumType' => 'App\\Enum\\PriorityEnum',
            ],
            'name' => [
                'fieldName' => 'name',
                'type' => 'string',
            ],
        ];

        $metadata = $this->createMetadataWithFieldMappings($fieldMappings);

        $this->entityManager->expects(self::once())
            ->method('getClassMetadata')
            ->with(TestHistoryTrackableEntity::class)
            ->willReturn($metadata);

        // When getting defined enums for entity
        $enums = $this->invokeMethod($this->parser, 'getDefinedEnumsForEntity', [TestHistoryTrackableEntity::class]);

        // Then enums with enumType should be detected
        self::assertArrayHasKey('status', $enums);
        self::assertEquals(TestStatusEnum::class, $enums['status']);
        self::assertArrayHasKey('priority', $enums);
        self::assertEquals('App\\Enum\\PriorityEnum', $enums['priority']);

        // And regular string fields should not be detected as enums
        self::assertArrayNotHasKey('name', $enums);
    }

    /**
     * Test that collections are automatically detected from associations.
     */
    public function testAutoDetectsCollectionsFromAssociations(): void
    {
        // Given an entity with collections
        $entity = new TestHistoryTrackableEntity();
        $entity->setRelatedEntities(new ArrayCollection());

        // And association mappings
        $associationMappings = [
            'relatedEntities' => [
                'fieldName' => 'relatedEntities',
                'targetEntity' => TestRelatedEntity::class,
                'type' => ClassMetadata::ONE_TO_MANY,
            ],
            'tags' => [
                'fieldName' => 'tags',
                'targetEntity' => 'App\\Entity\\Tag',
                'type' => ClassMetadata::MANY_TO_MANY,
            ],
            'parent' => [
                'fieldName' => 'parent',
                'targetEntity' => TestHistoryTrackableEntity::class,
                'type' => ClassMetadata::MANY_TO_ONE, // Not a collection
            ],
        ];

        $metadata = $this->createMock(ClassMetadata::class);

        $this->entityManager->expects(self::once())
            ->method('getClassMetadata')
            ->with(TestHistoryTrackableEntity::class)
            ->willReturn($metadata);

        $metadata->expects(self::once())
            ->method('getAssociationMappings')
            ->willReturn($associationMappings);

        $metadata->expects(self::exactly(3))
            ->method('isCollectionValuedAssociation')
            ->willReturnCallback(static fn ($fieldName) => in_array($fieldName, ['relatedEntities', 'tags'], true));

        // When getting defined collections
        $collections = $this->invokeMethod($this->parser, 'getDefinedCollections', [$entity]);

        // Then OneToMany and ManyToMany should be detected
        self::assertArrayHasKey('relatedEntities', $collections);
        self::assertInstanceOf(Collection::class, $collections['relatedEntities']);

        // And ManyToOne should not be detected (not a collection)
        self::assertArrayNotHasKey('parent', $collections);
    }

    /**
     * Test that pattern-based enum detection works with custom patterns.
     */

    /**
     * Test that ignored fields are excluded from detection.
     */
    public function testRespectsIgnoredFields(): void
    {
        // Given a parser with ignored fields
        $parser = new MetadataAwareDoctrineEventParser(
            $this->entityManager,
            ['status', 'relatedEntities'],
        );

        // And mappings that would normally be detected
        $fieldMappings = [
            'status' => [
                'fieldName' => 'status',
                'type' => 'string',
                'enumType' => TestStatusEnum::class,
            ],
            'priority' => [
                'fieldName' => 'priority',
                'type' => 'string',
                'enumType' => 'App\\Enum\\PriorityEnum',
            ],
        ];

        $associationMappings = [
            'relatedEntities' => [
                'fieldName' => 'relatedEntities',
                'targetEntity' => TestRelatedEntity::class,
                'type' => ClassMetadata::ONE_TO_MANY,
            ],
            'tags' => [
                'fieldName' => 'tags',
                'targetEntity' => 'App\\Entity\\Tag',
                'type' => ClassMetadata::MANY_TO_MANY,
            ],
        ];

        $metadataForEnums = $this->createMetadataWithFieldMappings($fieldMappings);
        $metadataForCollections = $this->createMock(ClassMetadata::class);

        $this->entityManager->expects(self::exactly(2))
            ->method('getClassMetadata')
            ->willReturnOnConsecutiveCalls($metadataForEnums, $metadataForCollections);

        $metadataForCollections->expects(self::once())
            ->method('getAssociationMappings')
            ->willReturn($associationMappings);

        $metadataForCollections->expects(self::exactly(1))
            ->method('isCollectionValuedAssociation')
            ->with('tags') // Only 'tags' is checked since 'relatedEntities' is ignored
            ->willReturn(true);

        $entity = new TestHistoryTrackableEntity();

        // When getting enums and collections
        $enums = $this->invokeMethod($parser, 'getDefinedEnumsForEntity', [TestHistoryTrackableEntity::class]);
        $collections = $this->invokeMethod($parser, 'getDefinedCollections', [$entity]);

        // Then ignored fields should be excluded
        self::assertArrayNotHasKey('status', $enums);
        self::assertArrayHasKey('priority', $enums);
        self::assertArrayNotHasKey('relatedEntities', $collections);
    }

    /**
     * Test that results are cached for performance.
     */
    public function testCachesEnumAndCollectionMappings(): void
    {
        // Given initial metadata setup
        $metadataForEnums = $this->createMetadataWithFieldMappings([
            'status' => ['fieldName' => 'status', 'enumType' => TestStatusEnum::class],
        ]);

        $metadataForCollections = $this->createMock(ClassMetadata::class);
        $metadataForCollections->expects(self::once())
            ->method('getAssociationMappings')
            ->willReturn([
                'relatedEntities' => ['fieldName' => 'relatedEntities', 'targetEntity' => TestRelatedEntity::class],
            ]);

        $metadataForCollections->expects(self::once())
            ->method('isCollectionValuedAssociation')
            ->willReturn(true);

        $this->entityManager->expects(self::exactly(2)) // Once for enums, once for collections
            ->method('getClassMetadata')
            ->willReturnOnConsecutiveCalls($metadataForEnums, $metadataForCollections);

        $entity = new TestHistoryTrackableEntity();
        $entity->setRelatedEntities(new ArrayCollection());

        // When calling methods multiple times
        $enums1 = $this->invokeMethod($this->parser, 'getDefinedEnumsForEntity', [TestHistoryTrackableEntity::class]);
        $enums2 = $this->invokeMethod($this->parser, 'getDefinedEnumsForEntity', [TestHistoryTrackableEntity::class]);

        $collections1 = $this->invokeMethod($this->parser, 'getDefinedCollections', [$entity]);
        $collections2 = $this->invokeMethod($this->parser, 'getDefinedCollections', [$entity]);

        // Then results should be identical (from cache)
        self::assertEquals($enums1, $enums2);
        self::assertEquals($collections1, $collections2);

        // And metadata should only be queried once (verified by expects)
    }

    /**
     * Test parsePreUpdateArgsWithEntity sets entity context correctly.
     */
    public function testParsePreUpdateArgsWithEntitySetsContext(): void
    {
        // Given an entity and change set
        $entity = new TestHistoryTrackableEntity();
        $oldValue = 'active'; // Doctrine change sets contain scalar values
        $newValue = 'inactive';

        $changeSet = [
            'status' => [$oldValue, $newValue],
        ];

        // Setup metadata for enum detection
        $metadata = $this->createMetadataWithFieldMappings([
            'status' => ['fieldName' => 'status', 'enumType' => TestStatusEnum::class],
        ]);

        $this->entityManager->expects(self::once())
            ->method('getClassMetadata')
            ->with(TestHistoryTrackableEntity::class)
            ->willReturn($metadata);

        // When parsing with entity context
        $changes = $this->parser->parsePreUpdateArgsWithEntity($entity, $changeSet);

        // Then changes should be parsed correctly
        self::assertCount(1, $changes);
        self::assertEquals('status', $changes[0]['field']);
        self::assertEquals('active', $changes[0]['oldValue']); // Should return the scalar value
        self::assertEquals('inactive', $changes[0]['newValue']);
        self::assertEquals(HistoryActionTypeEnum::UPDATE, $changes[0]['actionType']);
        self::assertEquals('status', $changes[0]['enumName']); // Currently returns field name, not enum class
    }

    /**
     * Test that pivot entities require manual configuration.
     */
    public function testPivotEntitiesRequireManualConfiguration(): void
    {
        // Given an entity
        $entity = new TestHistoryTrackableEntity();

        // When getting defined pivot entities
        $pivots = $this->invokeMethod($this->parser, 'getDefinedPivotEntities', [$entity]);

        // Then it should return empty array (default implementation)
        self::assertEmpty($pivots);
    }

    /**
     * Test handling of different Doctrine metadata accessor methods.
     */
    public function testHandlesDifferentMetadataAccessors(): void
    {
        // Test with getFieldMappings method available
        $metadata = $this->createMetadataWithFieldMappings([
            'status' => ['fieldName' => 'status'],
        ]);

        $this->entityManager->expects(self::once())
            ->method('getClassMetadata')
            ->willReturn($metadata);

        $enums = $this->invokeMethod($this->parser, 'getDefinedEnumsForEntity', [TestHistoryTrackableEntity::class]);
        self::assertIsArray($enums);
    }

    /**
     * Test pattern matching functionality.
     */

    /**
     * Test enum class name inference.
     */
    public function testInferEnumClassName(): void
    {
        // Test with existing TestStatusEnum
        $enumClass = $this->invokeMethod(
            $this->parser,
            'inferEnumClassName',
            [TestHistoryTrackableEntity::class, 'testStatus', []],
        );

        // Should find TestStatusEnum in the Fixtures namespace
        self::assertEquals(TestStatusEnum::class, $enumClass);

        // Test with non-existent enum
        $enumClass = $this->invokeMethod(
            $this->parser,
            'inferEnumClassName',
            [TestHistoryTrackableEntity::class, 'nonExistent', []],
        );

        self::assertNull($enumClass);
    }

    /**
     * Creates a mock ClassMetadata with fieldMappings property set.
     * Since getFieldMappings doesn't exist on ClassMetadata, we need to set the property.
     *
     * @param array<string, array<string, mixed>> $fieldMappings
     * @return ClassMetadata<IHistoryTrackable>&MockObject
     */
    private function createMetadataWithFieldMappings(array $fieldMappings): ClassMetadata
    {
        $metadata = $this->getMockBuilder(ClassMetadata::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Set the fieldMappings property directly
        $metadata->fieldMappings = $fieldMappings;

        return $metadata;
    }

    /**
     * Helper method to invoke protected/private methods.
     *
     * @param array<mixed> $parameters
     */
    private function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
