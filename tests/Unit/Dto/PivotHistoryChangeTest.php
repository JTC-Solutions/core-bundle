<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\Dto;

use JtcSolutions\Core\Dto\HistoryChange;
use JtcSolutions\Core\Dto\PivotHistoryChange;
use PHPUnit\Framework\TestCase;

class PivotHistoryChangeTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $field = 'role';
        $type = 'pivot_created';
        $from = null;
        $to = [
            'id' => 'role-uuid',
            'label' => 'admin',
            'pivotData' => [
                'permissions' => ['read', 'write'],
                'grantedAt' => '2024-06-19 14:30:00',
            ],
        ];
        $translationKey = 'pivot.role.created';
        $entityType = 'TestRole';
        $pivotEntityType = 'TestPivotEntity';
        $pivotData = [
            'permissions' => ['read', 'write'],
            'grantedAt' => '2024-06-19 14:30:00',
        ];

        $change = new PivotHistoryChange(
            $field,
            $type,
            $from,
            $to,
            $translationKey,
            $entityType,
            $pivotEntityType,
            $pivotData,
        );

        self::assertSame($field, $change->field);
        self::assertSame($type, $change->type);
        self::assertSame($from, $change->from);
        self::assertSame($to, $change->to);
        self::assertSame($translationKey, $change->translationKey);
        self::assertSame($entityType, $change->entityType);
        self::assertSame($pivotEntityType, $change->pivotEntityType);
        self::assertSame($pivotData, $change->pivotData);
    }

    public function testInheritsFromHistoryChange(): void
    {
        $change = new PivotHistoryChange(
            'role',
            'pivot_created',
            null,
            ['id' => 'test-id', 'label' => 'test'],
            'test.key',
            'TestEntity',
            'TestPivotEntity',
        );

        self::assertInstanceOf(HistoryChange::class, $change);
    }

    public function testWithNullPivotData(): void
    {
        $change = new PivotHistoryChange(
            'role',
            'pivot_deleted',
            ['id' => 'test-id', 'label' => 'test'],
            null,
            'pivot.role.deleted',
            'TestRole',
            'TestPivotEntity',
            null,
        );

        self::assertNull($change->pivotData);
    }

    public function testWithEmptyPivotData(): void
    {
        $change = new PivotHistoryChange(
            'role',
            'pivot_created',
            null,
            ['id' => 'test-id', 'label' => 'test'],
            'pivot.role.created',
            'TestRole',
            'TestPivotEntity',
            [],
        );

        self::assertSame([], $change->pivotData);
    }
}
