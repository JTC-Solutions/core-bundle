<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\Factory;

use Doctrine\ORM\EntityManagerInterface;
use JtcSolutions\Core\Dto\PivotHistoryChange;
use JtcSolutions\Core\Enum\HistoryActionTypeEnum;
use JtcSolutions\Core\Tests\Fixtures\History\TestPivotHistoryFactory;
use JtcSolutions\Core\Tests\Fixtures\History\TestUser;
use JtcSolutions\Core\Tests\Fixtures\History\TestUserHistory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BaseHistoryFactoryPivotTest extends TestCase
{
    private TestPivotHistoryFactory $factory;

    private EntityManagerInterface $entityManager;

    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->factory = new TestPivotHistoryFactory($this->entityManager, $this->translator);
    }

    public function testCreateFromUpdateWithPivotChange(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $userMock = $this->createMock(UserInterface::class);

        $pivotChange = [
            'field' => 'role',
            'oldValue' => null,
            'newValue' => [
                'id' => 'role-uuid',
                'label' => 'admin',
                'pivotData' => [
                    'permissions' => ['read', 'write'],
                    'grantedAt' => '2024-06-19 14:30:00',
                ],
            ],
            'relatedEntity' => 'TestRole',
            'actionType' => HistoryActionTypeEnum::PIVOT_CREATED,
            'pivotEntity' => 'TestPivotEntity',
        ];

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(TestUserHistory::class));

        $history = $this->factory->createFromUpdate($userMock, $user, [$pivotChange]);

        self::assertInstanceOf(TestUserHistory::class, $history);

        $changes = $history->getChanges();
        self::assertCount(1, $changes);
        self::assertInstanceOf(PivotHistoryChange::class, $changes[0]);

        $pivotHistoryChange = $changes[0];
        self::assertSame('role', $pivotHistoryChange->field);
        self::assertSame('pivot_created', $pivotHistoryChange->type);
        self::assertSame('TestRole', $pivotHistoryChange->entityType);
        self::assertSame('TestPivotEntity', $pivotHistoryChange->pivotEntityType);
        self::assertSame('pivot.role.created', $pivotHistoryChange->translationKey);
        self::assertIsArray($pivotHistoryChange->pivotData);
        self::assertSame(['read', 'write'], $pivotHistoryChange->pivotData['permissions']);
        self::assertSame('2024-06-19 14:30:00', $pivotHistoryChange->pivotData['grantedAt']);
    }

    public function testCreateFromUpdateWithPivotDeletion(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $userMock = $this->createMock(UserInterface::class);

        $pivotChange = [
            'field' => 'role',
            'oldValue' => [
                'id' => 'role-uuid',
                'label' => 'admin',
                'pivotData' => [
                    'permissions' => ['read', 'write', 'delete'],
                    'grantedAt' => '2024-06-19 14:30:00',
                ],
            ],
            'newValue' => null,
            'relatedEntity' => 'TestRole',
            'actionType' => HistoryActionTypeEnum::PIVOT_DELETED,
            'pivotEntity' => 'TestPivotEntity',
        ];

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(TestUserHistory::class));

        $history = $this->factory->createFromUpdate($userMock, $user, [$pivotChange]);

        $changes = $history->getChanges();
        self::assertCount(1, $changes);
        self::assertInstanceOf(PivotHistoryChange::class, $changes[0]);

        $pivotHistoryChange = $changes[0];
        self::assertSame('role', $pivotHistoryChange->field);
        self::assertSame('pivot_deleted', $pivotHistoryChange->type);
        self::assertSame('pivot.role.deleted', $pivotHistoryChange->translationKey);
        self::assertIsArray($pivotHistoryChange->pivotData);
        self::assertSame(['read', 'write', 'delete'], $pivotHistoryChange->pivotData['permissions']);
    }

    public function testCreateFromUpdateWithPivotUpdate(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $userMock = $this->createMock(UserInterface::class);

        $pivotChange = [
            'field' => 'role',
            'oldValue' => [
                'permissions' => [
                    'from' => 'read',
                    'to' => 'read,write,delete',
                ],
            ],
            'newValue' => [
                'id' => 'role-uuid',
                'label' => 'admin',
                'pivotData' => [
                    'permissions' => ['read', 'write', 'delete'],
                    'grantedAt' => '2024-06-19 14:30:00',
                ],
            ],
            'relatedEntity' => 'TestRole',
            'actionType' => HistoryActionTypeEnum::PIVOT_UPDATED,
            'pivotEntity' => 'TestPivotEntity',
        ];

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(TestUserHistory::class));

        $history = $this->factory->createFromUpdate($userMock, $user, [$pivotChange]);

        $changes = $history->getChanges();
        self::assertCount(1, $changes);
        self::assertInstanceOf(PivotHistoryChange::class, $changes[0]);

        $pivotHistoryChange = $changes[0];
        self::assertSame('role', $pivotHistoryChange->field);
        self::assertSame('pivot_updated', $pivotHistoryChange->type);
        self::assertSame('pivot.role.updated', $pivotHistoryChange->translationKey);
        self::assertIsArray($pivotHistoryChange->pivotData);
        self::assertSame(['read', 'write', 'delete'], $pivotHistoryChange->pivotData['permissions']);
    }

    public function testCreateFromUpdateWithMixedChanges(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $userMock = $this->createMock(UserInterface::class);

        $regularChange = [
            'field' => 'email',
            'oldValue' => 'old@example.com',
            'newValue' => 'new@example.com',
            'relatedEntity' => null,
            'actionType' => HistoryActionTypeEnum::UPDATE,
        ];

        $pivotChange = [
            'field' => 'role',
            'oldValue' => null,
            'newValue' => [
                'id' => 'role-uuid',
                'label' => 'admin',
                'pivotData' => [
                    'permissions' => ['read'],
                ],
            ],
            'relatedEntity' => 'TestRole',
            'actionType' => HistoryActionTypeEnum::PIVOT_CREATED,
            'pivotEntity' => 'TestPivotEntity',
        ];

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(TestUserHistory::class));

        $history = $this->factory->createFromUpdate($userMock, $user, [$regularChange, $pivotChange]);

        $changes = $history->getChanges();
        self::assertCount(2, $changes);

        // First should be regular change
        self::assertSame('email', $changes[0]->field);
        self::assertSame('update', $changes[0]->type);

        // Second should be pivot change
        self::assertInstanceOf(PivotHistoryChange::class, $changes[1]);
        self::assertSame('role', $changes[1]->field);
        self::assertSame('pivot_created', $changes[1]->type);
    }
}
