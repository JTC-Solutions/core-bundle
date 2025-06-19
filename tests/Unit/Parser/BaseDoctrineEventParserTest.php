<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\Parser;

use DateTime;
use DateTimeImmutable;
use JtcSolutions\Core\Enum\HistoryActionTypeEnum;
use JtcSolutions\Core\Tests\Fixtures\History\TestItem;
use JtcSolutions\Core\Tests\Fixtures\History\TestPersistentCollection;
use JtcSolutions\Core\Tests\Fixtures\History\TestStatusEnum;
use JtcSolutions\Core\Tests\Fixtures\History\TestUser;
use JtcSolutions\Core\Tests\Fixtures\History\TestUserDoctrineEventParser;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BaseDoctrineEventParserTest extends TestCase
{
    private TestUserDoctrineEventParser $parser;

    private TestUserDoctrineEventParser $parserWithIgnoredFields;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TestUserDoctrineEventParser();
        $this->parserWithIgnoredFields = new TestUserDoctrineEventParser(['updatedAt', 'version']);
    }

    public function testParsePreUpdateArgsWithScalarFieldChanges(): void
    {
        // Arrange
        $changeSet = [
            'firstname' => ['John', 'Jane'],
            'email' => ['john.doe@example.com', 'jane.smith@example.com'],
            'isActive' => [true, false],
            'lastname' => ['Doe', null],
        ];

        // Act
        $result = $this->parser->parsePreUpdateArgs($changeSet);

        // Assert - 4 changes, lastname with null value last
        self::assertCount(4, $result);

        // Check string field change
        self::assertEquals([
            'field' => 'firstname',
            'oldValue' => 'John',
            'newValue' => 'Jane',
            'relatedEntity' => null,
            'actionType' => HistoryActionTypeEnum::UPDATE,
        ], $result[0]);

        // Check email field change
        self::assertEquals([
            'field' => 'email',
            'oldValue' => 'john.doe@example.com',
            'newValue' => 'jane.smith@example.com',
            'relatedEntity' => null,
            'actionType' => HistoryActionTypeEnum::UPDATE,
        ], $result[1]);

        // Check boolean field change
        self::assertEquals([
            'field' => 'isActive',
            'oldValue' => '1',
            'newValue' => '',
            'relatedEntity' => null,
            'actionType' => HistoryActionTypeEnum::UPDATE,
        ], $result[2]);

        // Check null value handling (lastname to null)
        self::assertEquals([
            'field' => 'lastname',
            'oldValue' => 'Doe',
            'newValue' => null,
            'relatedEntity' => null,
            'actionType' => HistoryActionTypeEnum::UPDATE,
        ], $result[3]);
    }

    public function testParsePreUpdateArgsWithDateTimeChanges(): void
    {
        // Arrange
        $oldCreatedAt = new DateTime('2024-01-01 10:00:00');
        $newCreatedAt = new DateTimeImmutable('2024-02-15 15:30:45');

        $changeSet = [
            'createdAt' => [$oldCreatedAt, $newCreatedAt],
        ];

        // Act
        $result = $this->parser->parsePreUpdateArgs($changeSet);

        // Assert
        self::assertCount(1, $result);

        self::assertEquals([
            'field' => 'createdAt',
            'oldValue' => '01.01.2024 10:00:00',
            'newValue' => '15.02.2024 15:30:45',
            'relatedEntity' => null,
            'actionType' => HistoryActionTypeEnum::UPDATE,
        ], $result[0]);
    }

    public function testParsePreUpdateArgsWithEntityRelationChanges(): void
    {
        // Arrange
        $oldCreatedBy = new TestUser(firstname: 'Admin', lastname: 'User', email: 'admin@example.com');
        $newCreatedBy = new TestUser(firstname: 'Manager', lastname: 'User', email: 'manager@example.com');
        $oldCreatedById = $oldCreatedBy->getId()->toString();
        $newCreatedById = $newCreatedBy->getId()->toString();

        $changeSet = [
            'createdBy' => [$oldCreatedBy, $newCreatedBy],
            'createdBy2' => [$oldCreatedBy, null],
            'createdBy3' => [null, $newCreatedBy],
        ];

        // Act
        $result = $this->parser->parsePreUpdateArgs($changeSet);

        // Assert
        self::assertCount(3, $result);

        // Entity to entity change
        self::assertEquals([
            'field' => 'createdBy',
            'oldValue' => [
                'id' => $oldCreatedById,
                'label' => 'Admin User (admin@example.com)',
            ],
            'newValue' => [
                'id' => $newCreatedById,
                'label' => 'Manager User (manager@example.com)',
            ],
            'relatedEntity' => 'TestUser',
            'actionType' => HistoryActionTypeEnum::CHANGE_RELATION,
        ], $result[0]);

        // Entity to null change
        self::assertEquals([
            'field' => 'createdBy2',
            'oldValue' => [
                'id' => $oldCreatedById,
                'label' => 'Admin User (admin@example.com)',
            ],
            'newValue' => null,
            'relatedEntity' => 'TestUser',
            'actionType' => HistoryActionTypeEnum::CHANGE_RELATION,
        ], $result[1]);

        // Null to entity change
        self::assertEquals([
            'field' => 'createdBy3',
            'oldValue' => null,
            'newValue' => [
                'id' => $newCreatedById,
                'label' => 'Manager User (manager@example.com)',
            ],
            'relatedEntity' => 'TestUser',
            'actionType' => HistoryActionTypeEnum::CHANGE_RELATION,
        ], $result[2]);
    }

    public function testParsePreUpdateArgsWithItemRelation(): void
    {
        // Arrange - Item doesn't have special label format like User
        $item = new TestItem(name: 'Laptop', quantity: 2, price: 999.99);
        $itemId = $item->getId()->toString();

        $changeSet = [
            'favoriteItem' => [null, $item],
        ];

        // Act
        $result = $this->parser->parsePreUpdateArgs($changeSet);

        // Assert
        self::assertCount(1, $result);
        self::assertEquals([
            'field' => 'favoriteItem',
            'oldValue' => null,
            'newValue' => [
                'id' => $itemId,
                'label' => 'Laptop (Qty: 2, Price: €999.99)',
            ],
            'relatedEntity' => 'TestItem',
            'actionType' => HistoryActionTypeEnum::CHANGE_RELATION,
        ], $result[0]);
    }

    public function testParsePreUpdateArgsWithEnumChanges(): void
    {
        // Arrange
        $changeSet = [
            'status' => [TestStatusEnum::DRAFT->value, TestStatusEnum::ACTIVE->value],
            'nonEnumField' => ['draft', 'active'], // This should be treated as scalar
        ];

        // Act
        $result = $this->parser->parsePreUpdateArgs($changeSet);

        // Assert
        self::assertCount(2, $result);

        // Enum field change
        self::assertEquals([
            'field' => 'status',
            'oldValue' => 'draft',
            'newValue' => 'active',
            'enumName' => 'status',
            'actionType' => HistoryActionTypeEnum::UPDATE,
        ], $result[0]);

        // Non-enum field (treated as scalar)
        self::assertEquals([
            'field' => 'nonEnumField',
            'oldValue' => 'draft',
            'newValue' => 'active',
            'relatedEntity' => null,
            'actionType' => HistoryActionTypeEnum::UPDATE,
        ], $result[1]);
    }

    public function testParsePreUpdateArgsWithIgnoredFields(): void
    {
        // Arrange
        $changeSet = [
            'name' => ['Old', 'New'],
            'updatedAt' => [new DateTime('2024-01-01'), new DateTime('2024-01-02')],
            'version' => [1, 2],
            'price' => [100.0, 200.0],
        ];

        // Act
        $result = $this->parserWithIgnoredFields->parsePreUpdateArgs($changeSet);

        // Assert
        self::assertCount(2, $result); // Only name and price, updatedAt and version are ignored

        $fieldNames = array_column($result, 'field');
        self::assertContains('name', $fieldNames);
        self::assertContains('price', $fieldNames);
        self::assertNotContains('updatedAt', $fieldNames);
        self::assertNotContains('version', $fieldNames);
    }

    public function testParsePreUpdateArgsWithUnchangedValues(): void
    {
        // Arrange
        $sameDate = new DateTime('2024-01-01 10:00:00');
        $sameDate2 = new DateTime('2024-01-01 10:00:00');

        $changeSet = [
            'name' => ['Same', 'Same'],
            'quantity' => [10, 10],
            'createdAt' => [$sameDate, $sameDate2], // Same timestamp
            'price' => [100.0, 100.0],
        ];

        // Act
        $result = $this->parser->parsePreUpdateArgs($changeSet);

        // Assert
        self::assertCount(0, $result); // No changes should be detected
    }

    public function testParsePreUpdateArgsWithMixedFieldTypes(): void
    {
        // Arrange
        $user2 = new TestUser();
        $changeSet = [
            'name' => ['Old', 'New'],
            'createdBy' => [null, $user2],
            'status' => [TestStatusEnum::DRAFT->value, TestStatusEnum::PENDING->value],
            'createdAt' => [new DateTime('2024-01-01'), new DateTime('2024-02-01')],
            'quantity' => [5, 10],
        ];

        // Act
        $result = $this->parser->parsePreUpdateArgs($changeSet);

        // Assert
        self::assertCount(5, $result);

        // Verify each type is parsed correctly
        $types = array_column($result, 'actionType');
        self::assertContains(HistoryActionTypeEnum::UPDATE, $types);
        self::assertContains(HistoryActionTypeEnum::CHANGE_RELATION, $types);
    }

    public function testParsePreUpdateScheduledCollectionUpdatesWithAdditionsAndRemovals(): void
    {
        // Arrange
        $user = new TestUser();

        $item1 = new TestItem(name: 'Laptop', quantity: 1, price: 1200.00);
        $item2 = new TestItem(name: 'Mouse', quantity: 2, price: 25.99);
        $item3 = new TestItem(name: 'Keyboard', quantity: 1, price: 89.99);

        // Create test collection
        $collection = new TestPersistentCollection(
            $user,
            ['fieldName' => 'items'],
            [$item1, $item2], // insertDiff
            [$item3], // deleteDiff
        );

        // Act
        /** @phpstan-ignore-next-line argument.type */
        $result = $this->parser->parsePreUpdateScheduledCollectionUpdates($user, [$collection]);

        // Assert
        self::assertCount(3, $result); // 1 removal + 2 additions

        // Check removal
        self::assertEquals([
            'field' => 'items',
            'oldValue' => [
                'id' => $item3->getId()->toString(),
                'label' => 'Keyboard (Qty: 1, Price: €89.99)',
            ],
            'newValue' => null,
            'relatedEntity' => 'TestItem',
            'actionType' => HistoryActionTypeEnum::REMOVED_FROM_COLLECTION,
        ], $result[0]);

        // Check additions
        self::assertEquals([
            'field' => 'items',
            'oldValue' => null,
            'newValue' => [
                'id' => $item1->getId()->toString(),
                'label' => 'Laptop (Qty: 1, Price: €1200)',
            ],
            'relatedEntity' => 'TestItem',
            'actionType' => HistoryActionTypeEnum::ADDED_TO_COLLECTION,
        ], $result[1]);

        self::assertEquals([
            'field' => 'items',
            'oldValue' => null,
            'newValue' => [
                'id' => $item2->getId()->toString(),
                'label' => 'Mouse (Qty: 2, Price: €25.99)',
            ],
            'relatedEntity' => 'TestItem',
            'actionType' => HistoryActionTypeEnum::ADDED_TO_COLLECTION,
        ], $result[2]);
    }

    public function testParsePreUpdateScheduledCollectionUpdatesWithDifferentOwner(): void
    {
        // Arrange
        $user = new TestUser();
        $differentUser = new TestUser();

        // Create test collection with different owner
        $collection = new TestPersistentCollection(
            $differentUser, // Different owner
            ['fieldName' => 'items'],
            [],
            [],
        );

        // Act
        /** @phpstan-ignore-next-line argument.type */
        $result = $this->parser->parsePreUpdateScheduledCollectionUpdates($user, [$collection]);

        // Assert
        self::assertCount(0, $result); // Should skip collections with different owner
    }

    public function testParsePreUpdateScheduledCollectionUpdatesWithDifferentField(): void
    {
        // Arrange
        $user = new TestUser();

        // Create test collection with different field name
        $collection = new TestPersistentCollection(
            $user,
            ['fieldName' => 'otherField'], // Different field name
            [],
            [],
        );

        // Act
        /** @phpstan-ignore-next-line argument.type */
        $result = $this->parser->parsePreUpdateScheduledCollectionUpdates($user, [$collection]);

        // Assert
        self::assertCount(0, $result); // Should skip collections with different field name
    }

    public function testParsePreUpdateScheduledCollectionUpdatesWithEmptyChanges(): void
    {
        // Arrange
        $user = new TestUser();

        // Create test collection with no changes
        $collection = new TestPersistentCollection(
            $user,
            ['fieldName' => 'items'],
            [], // empty insertDiff
            [], // empty deleteDiff
        );

        // Act
        /** @phpstan-ignore-next-line argument.type */
        $result = $this->parser->parsePreUpdateScheduledCollectionUpdates($user, [$collection]);

        // Assert
        self::assertCount(0, $result); // No changes to report
    }

    public function testParsePreUpdateScheduledCollectionUpdatesWithMultipleCollections(): void
    {
        // Arrange
        $user = new TestUser();
        $item = new TestItem(name: 'Test Item', quantity: 1, price: 19.99);

        // Create test collections
        $collection1 = new TestPersistentCollection(
            $user,
            ['fieldName' => 'items'],
            [$item],
            [],
        );

        $collection2 = new TestPersistentCollection(
            $user,
            ['fieldName' => 'otherField'], // Different field, should be ignored
            [],
            [],
        );

        // Act
        /** @phpstan-ignore-next-line argument.type */
        $result = $this->parser->parsePreUpdateScheduledCollectionUpdates($user, [$collection1, $collection2]);

        // Assert
        self::assertCount(1, $result); // Only one change from collection1
        /** @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible */
        self::assertEquals('items', $result[0]['field']);
    }

    /**
     * @dataProvider scalarValueProvider
     */
    public function testParseScalarValue(mixed $input, ?string $expected): void
    {
        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->parser);
        $method = $reflection->getMethod('parseScalarValue');
        $method->setAccessible(true);

        $result = $method->invoke($this->parser, $input);
        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{mixed, ?string}>
     */
    public function scalarValueProvider(): array
    {
        return [
            'null value' => [null, null],
            'string value' => ['test string', 'test string'],
            'integer value' => [42, '42'],
            'float value' => [3.14, '3.14'],
            'boolean true' => [true, '1'],
            'boolean false' => [false, ''],
            'datetime value' => [new DateTime('2024-01-15 14:30:45'), '15.01.2024 14:30:45'],
            'zero integer' => [0, '0'],
            'negative integer' => [-10, '-10'],
            'zero float' => [0.0, '0'],
        ];
    }

    public function testAreValuesTheSameWithVariousTypes(): void
    {
        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->parser);
        $method = $reflection->getMethod('areValuesTheSame');
        $method->setAccessible(true);

        // Test scalar values
        self::assertTrue($method->invoke($this->parser, 'same', 'same'));
        self::assertTrue($method->invoke($this->parser, 42, 42));
        self::assertTrue($method->invoke($this->parser, null, null));
        self::assertFalse($method->invoke($this->parser, 'different', 'values'));

        // Test DateTime values with same timestamp
        $date1 = new DateTime('2024-01-01 10:00:00');
        $date2 = new DateTime('2024-01-01 10:00:00');
        self::assertTrue($method->invoke($this->parser, $date1, $date2));

        // Test DateTime values with different timestamp
        $date3 = new DateTime('2024-01-01 10:00:01');
        self::assertFalse($method->invoke($this->parser, $date1, $date3));

        // Test mixed types
        self::assertFalse($method->invoke($this->parser, '42', 42));
        self::assertFalse($method->invoke($this->parser, 0, false));
    }

    public function testIsAnyValueEntityReference(): void
    {
        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->parser);
        $method = $reflection->getMethod('isAnyValueEntityReference');
        $method->setAccessible(true);

        $user2 = new TestUser();

        // Test with entity reference
        self::assertTrue($method->invoke($this->parser, [$user2, null]));
        self::assertTrue($method->invoke($this->parser, [null, $user2]));
        self::assertTrue($method->invoke($this->parser, [$user2, $user2]));

        // Test without entity reference
        self::assertFalse($method->invoke($this->parser, ['string', 42]));
        self::assertFalse($method->invoke($this->parser, [null, null]));
        self::assertFalse($method->invoke($this->parser, ['scalar', 'value']));
    }

    public function testParsePreUpdateArgsWithComplexRealWorldScenario(): void
    {
        // Arrange - simulate a real-world user update
        $oldCreatedBy = new TestUser(firstname: 'Admin', lastname: 'User', email: 'admin@example.com');
        $newCreatedBy = new TestUser(firstname: 'Manager', lastname: 'User', email: 'manager@example.com');

        $changeSet = [
            'firstname' => ['John', 'Johnny'],
            'lastname' => ['Doe', 'Smith'],
            'email' => ['john@example.com', 'johnny.smith@example.com'],
            'isActive' => [true, true], // No change
            'isActive2' => [false, true], // Changed
            'createdBy' => [$oldCreatedBy, $newCreatedBy],
            'status' => [TestStatusEnum::ACTIVE->value, TestStatusEnum::ARCHIVED->value],
            'updatedAt' => [new DateTime('2024-01-01'), new DateTime('2024-01-02')], // Ignored field
            'createdAt' => [new DateTime('2023-01-01'), new DateTime('2023-01-01')], // Same timestamp
        ];

        // Act
        $result = $this->parserWithIgnoredFields->parsePreUpdateArgs($changeSet);

        // Assert
        self::assertCount(6, $result); // isActive unchanged, updatedAt ignored, createdAt unchanged

        // Verify the changes are in correct order and format
        $fields = array_column($result, 'field');
        self::assertEquals(['firstname', 'lastname', 'email', 'isActive2', 'createdBy', 'status'], $fields);

        // Verify action types
        $actionTypes = array_column($result, 'actionType');
        self::assertEquals([
            HistoryActionTypeEnum::UPDATE,
            HistoryActionTypeEnum::UPDATE,
            HistoryActionTypeEnum::UPDATE,
            HistoryActionTypeEnum::UPDATE,
            HistoryActionTypeEnum::CHANGE_RELATION,
            HistoryActionTypeEnum::UPDATE,
        ], $actionTypes);
    }
}
