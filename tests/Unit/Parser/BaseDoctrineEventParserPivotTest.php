<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\Parser;

use DateTime;
use InvalidArgumentException;
use JtcSolutions\Core\Enum\HistoryActionTypeEnum;
use JtcSolutions\Core\Tests\Fixtures\History\TestHistoryTrackableEntity;
use JtcSolutions\Core\Tests\Fixtures\History\TestPivotDoctrineEventParser;
use JtcSolutions\Core\Tests\Fixtures\History\TestPivotEntity;
use JtcSolutions\Core\Tests\Fixtures\History\TestRole;
use JtcSolutions\Core\Tests\Fixtures\History\TestUser;
use PHPUnit\Framework\TestCase;

class BaseDoctrineEventParserPivotTest extends TestCase
{
    private TestPivotDoctrineEventParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TestPivotDoctrineEventParser();
    }

    public function testGetDefinedPivotEntitiesForUser(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $pivotEntities = $this->parser->getDefinedPivotEntities($user);

        self::assertArrayHasKey('role', $pivotEntities);
        self::assertSame(TestPivotEntity::class, $pivotEntities['role']);
    }

    public function testGetDefinedPivotEntitiesForNonUser(): void
    {
        $historyTrackableEntity = new TestHistoryTrackableEntity();
        $pivotEntities = $this->parser->getDefinedPivotEntities($historyTrackableEntity);

        self::assertSame([], $pivotEntities);
    }

    public function testParsePivotEntityCreation(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $role = new TestRole('admin', 'Administrator role');
        $permissions = ['read', 'write', 'delete'];
        $grantedAt = new DateTime('2024-06-19 14:30:00');

        $pivotEntity = new TestPivotEntity($user, $role, $permissions, $grantedAt);

        $change = $this->parser->parsePivotEntityChange(
            $pivotEntity,
            HistoryActionTypeEnum::PIVOT_CREATED,
        );

        self::assertSame('role', $change['field']);
        self::assertNull($change['oldValue']);
        self::assertIsArray($change['newValue']);
        self::assertSame($role->getId()->toString(), $change['newValue']['id']);
        self::assertSame('admin', $change['newValue']['label']);
        self::assertArrayHasKey('pivotData', $change['newValue']);
        self::assertSame($permissions, $change['newValue']['pivotData']['permissions']);
        self::assertSame('2024-06-19 14:30:00', $change['newValue']['pivotData']['grantedAt']);
        self::assertSame('TestRole', $change['relatedEntity']);
        self::assertSame(HistoryActionTypeEnum::PIVOT_CREATED, $change['actionType']);
        self::assertSame('TestPivotEntity', $change['pivotEntity']);
    }

    public function testParsePivotEntityDeletion(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $role = new TestRole('admin', 'Administrator role');
        $permissions = ['read', 'write'];

        $pivotEntity = new TestPivotEntity($user, $role, $permissions);

        $change = $this->parser->parsePivotEntityChange(
            $pivotEntity,
            HistoryActionTypeEnum::PIVOT_DELETED,
        );

        self::assertSame('role', $change['field']);
        self::assertIsArray($change['oldValue']);
        self::assertSame($role->getId()->toString(), $change['oldValue']['id']);
        self::assertSame('admin', $change['oldValue']['label']);
        self::assertArrayHasKey('pivotData', $change['oldValue']);
        self::assertSame($permissions, $change['oldValue']['pivotData']['permissions']);
        self::assertNull($change['newValue']);
        self::assertSame('TestRole', $change['relatedEntity']);
        self::assertSame(HistoryActionTypeEnum::PIVOT_DELETED, $change['actionType']);
        self::assertSame('TestPivotEntity', $change['pivotEntity']);
    }

    public function testParsePivotEntityUpdate(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $role = new TestRole('admin', 'Administrator role');
        $pivotEntity = new TestPivotEntity($user, $role, ['read']);

        $changeSet = [
            'permissions' => [['read'], ['read', 'write', 'delete']],
            'grantedAt' => [new DateTime('2024-06-19 14:30:00'), new DateTime('2024-06-20 10:00:00')],
        ];

        $change = $this->parser->parsePivotEntityChange(
            $pivotEntity,
            HistoryActionTypeEnum::PIVOT_UPDATED,
            $changeSet,
        );

        self::assertSame('role', $change['field']);
        self::assertIsArray($change['oldValue']);
        self::assertArrayHasKey('permissions', $change['oldValue']);
        self::assertArrayHasKey('grantedAt', $change['oldValue']);
        self::assertIsArray($change['newValue']);
        self::assertSame($role->getId()->toString(), $change['newValue']['id']);
        self::assertSame('admin', $change['newValue']['label']);
        self::assertArrayHasKey('pivotData', $change['newValue']);
        self::assertSame('TestRole', $change['relatedEntity']);
        self::assertSame(HistoryActionTypeEnum::PIVOT_UPDATED, $change['actionType']);
        self::assertSame('TestPivotEntity', $change['pivotEntity']);
    }

    public function testParsePivotEntityReference(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $role = new TestRole('admin', 'Administrator role');
        $permissions = ['read', 'write'];
        $pivotEntity = new TestPivotEntity($user, $role, $permissions);

        $reference = $this->parser->parsePivotEntityReference($pivotEntity);

        self::assertIsArray($reference);
        self::assertSame($role->getId()->toString(), $reference['id']);
        self::assertSame('admin', $reference['label']);
        self::assertArrayHasKey('pivotData', $reference);
        self::assertSame($permissions, $reference['pivotData']['permissions']);
        self::assertNotEmpty($reference['pivotData']['grantedAt']);
    }

    public function testParsePivotEntityReferenceWithEmptyPivotData(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $role = new TestRole('admin', 'Administrator role');
        $pivotEntity = new TestPivotEntity($user, $role, []);

        $reference = $this->parser->parsePivotEntityReference($pivotEntity);

        self::assertIsArray($reference);
        self::assertSame($role->getId()->toString(), $reference['id']);
        self::assertSame('admin', $reference['label']);
        self::assertArrayHasKey('pivotData', $reference);
        self::assertSame([], $reference['pivotData']['permissions']);
    }

    public function testInvalidActionTypeThrowsException(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $role = new TestRole('admin', 'Administrator role');
        $pivotEntity = new TestPivotEntity($user, $role);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid action type for pivot entity: update');

        $this->parser->parsePivotEntityChange(
            $pivotEntity,
            HistoryActionTypeEnum::UPDATE,
        );
    }
}
