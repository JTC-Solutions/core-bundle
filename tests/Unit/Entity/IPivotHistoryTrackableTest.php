<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\Entity;

use DateTime;
use JtcSolutions\Core\Tests\Fixtures\History\TestPivotEntity;
use JtcSolutions\Core\Tests\Fixtures\History\TestRole;
use JtcSolutions\Core\Tests\Fixtures\History\TestUser;
use PHPUnit\Framework\TestCase;

class IPivotHistoryTrackableTest extends TestCase
{
    public function testPivotEntityImplementsInterface(): void
    {
        $user = new TestUser(null, 'testuser', 'user', 'test@example.com');
        $role = new TestRole('admin', 'Administrator role');
        $permissions = ['read', 'write', 'delete'];
        $grantedAt = new DateTime('2024-06-19 14:30:00');

        $pivotEntity = new TestPivotEntity($user, $role, $permissions, $grantedAt);

        self::assertSame($user, $pivotEntity->getHistoryOwner());
        self::assertSame($role, $pivotEntity->getHistoryTarget());
        self::assertSame('role', $pivotEntity->getRelationshipType());

        $expectedPivotData = [
            'permissions' => $permissions,
            'grantedAt' => '2024-06-19 14:30:00',
        ];
        self::assertSame($expectedPivotData, $pivotEntity->getPivotData());
    }

    public function testPivotEntityDataChanges(): void
    {
        $user = new TestUser(null, 'testuser', 'user', 'test@example.com');
        $role = new TestRole('admin', 'Administrator role');
        $pivotEntity = new TestPivotEntity($user, $role);

        // Initial state
        self::assertSame([], $pivotEntity->getPermissions());

        // Update permissions
        $newPermissions = ['read', 'write'];
        $pivotEntity->setPermissions($newPermissions);
        self::assertSame($newPermissions, $pivotEntity->getPermissions());

        // Update granted date
        $newGrantedAt = new DateTime('2024-06-20 10:00:00');
        $pivotEntity->setGrantedAt($newGrantedAt);
        self::assertSame($newGrantedAt, $pivotEntity->getGrantedAt());

        // Verify pivot data reflects changes
        $expectedPivotData = [
            'permissions' => $newPermissions,
            'grantedAt' => '2024-06-20 10:00:00',
        ];
        self::assertSame($expectedPivotData, $pivotEntity->getPivotData());
    }

    public function testPivotEntityWithEmptyData(): void
    {
        $user = new TestUser(null, 'testuser', 'user', 'test@example.com');
        $role = new TestRole('guest', 'Guest role');
        $pivotEntity = new TestPivotEntity($user, $role, []);

        $pivotData = $pivotEntity->getPivotData();
        self::assertArrayHasKey('permissions', $pivotData);
        self::assertArrayHasKey('grantedAt', $pivotData);
        self::assertSame([], $pivotData['permissions']);
        self::assertNotEmpty($pivotData['grantedAt']); // Should have a timestamp
    }
}
