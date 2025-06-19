<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\Factory;

use Doctrine\ORM\EntityManagerInterface;
use JtcSolutions\Core\Dto\HistoryChange;
use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Enum\HistoryActionTypeEnum;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use JtcSolutions\Core\Factory\BaseHistoryFactory;
use JtcSolutions\Core\Tests\Fixtures\History\TestItem;
use JtcSolutions\Core\Tests\Fixtures\History\TestStatusEnum;
use JtcSolutions\Core\Tests\Fixtures\History\TestUser;
use JtcSolutions\Core\Tests\Fixtures\History\TestUserHistory;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Unit tests for BaseHistoryFactory functionality.
 * Tests factory behavior, change processing, and history entity creation.
 */
class BaseHistoryFactoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private TranslatorInterface $translator;

    private UserInterface $user;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->user = $this->createMock(UserInterface::class);
    }

    public function testConstructorThrowsExceptionWhenClassNameNotSet(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Class name must be set in child class');

        new TestHistoryFactoryWithoutClassName($this->entityManager, $this->translator);
    }

    public function testConstructorSucceedsWhenClassNameIsSet(): void
    {
        $factory = new TestHistoryFactory($this->entityManager, $this->translator);

        self::assertInstanceOf(BaseHistoryFactory::class, $factory);
    }

    public function testSupportsReturnsTrueForSupportedEntity(): void
    {
        $factory = new TestHistoryFactory($this->entityManager, $this->translator);
        $user = new TestUser();

        $result = $factory->supports($user);

        self::assertTrue($result);
    }

    public function testSupportsReturnsFalseForUnsupportedEntity(): void
    {
        $factory = new TestHistoryFactory($this->entityManager, $this->translator);
        $item = new TestItem();

        $result = $factory->supports($item);

        self::assertFalse($result);
    }

    public function testCreateFromCreateWithSimpleEntityReference(): void
    {
        $factory = new TestHistoryFactory($this->entityManager, $this->translator);
        $user = new TestUser();

        $change = [
            'id' => $user->getId()->toString(),
            'label' => null,
        ];

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $result = $factory->createFromCreate($this->user, $user, $change);

        self::assertInstanceOf(TestUserHistory::class, $result);
        self::assertEquals($this->user, $result->getCreatedBy());
        self::assertEquals(HistorySeverityEnum::LOW, $result->getSeverity());

        $changes = $result->getChanges();
        self::assertCount(1, $changes);
        self::assertEquals('entity', $changes[0]->field);
        self::assertEquals(HistoryActionTypeEnum::CREATE->value, $changes[0]->type);
        self::assertNull($changes[0]->from);
        self::assertEquals($change, $changes[0]->to);
    }

    public function testCreateFromCreateWithNullUser(): void
    {
        $factory = new TestHistoryFactory($this->entityManager, $this->translator);
        $user = new TestUser();

        $change = [
            'id' => $user->getId()->toString(),
            'label' => null,
        ];

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $result = $factory->createFromCreate(null, $user, $change);

        self::assertInstanceOf(TestUserHistory::class, $result);
        self::assertNull($result->getCreatedBy());
    }

    public function testCreateFromUpdateWithScalarChanges(): void
    {
        $factory = new TestHistoryFactory($this->entityManager, $this->translator);
        $user = new TestUser();

        $changes = [
            [
                'field' => 'firstname',
                'oldValue' => 'John',
                'newValue' => 'Jane',
                'actionType' => HistoryActionTypeEnum::UPDATE,
            ],
            [
                'field' => 'email',
                'oldValue' => 'john@example.com',
                'newValue' => 'jane@example.com',
                'actionType' => HistoryActionTypeEnum::UPDATE,
                'relatedEntity' => 'TestUser',
            ],
        ];

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::never())->method('flush'); // No flush on update

        $result = $factory->createFromUpdate($this->user, $user, $changes);

        self::assertInstanceOf(TestUserHistory::class, $result);

        $historyChanges = $result->getChanges();
        self::assertCount(2, $historyChanges);

        // Check first change
        self::assertEquals('firstname', $historyChanges[0]->field);
        self::assertEquals(HistoryActionTypeEnum::UPDATE->value, $historyChanges[0]->type);
        self::assertEquals('John', $historyChanges[0]->from);
        self::assertEquals('Jane', $historyChanges[0]->to);
        self::assertEquals('TestUser', $historyChanges[0]->entityType);

        // Check second change
        self::assertEquals('email', $historyChanges[1]->field);
        self::assertEquals('TestUser', $historyChanges[1]->entityType);
    }

    public function testCreateFromUpdateWithEnumChanges(): void
    {
        $factory = new TestHistoryFactory($this->entityManager, $this->translator);
        $user = new TestUser();

        $changes = [
            [
                'field' => 'status',
                'oldValue' => TestStatusEnum::DRAFT->value,
                'newValue' => TestStatusEnum::ACTIVE->value,
                'actionType' => HistoryActionTypeEnum::UPDATE,
                'enumName' => 'status',
            ],
        ];

        $this->entityManager->expects(self::once())->method('persist');

        $result = $factory->createFromUpdate($this->user, $user, $changes);

        $historyChanges = $result->getChanges();
        self::assertCount(1, $historyChanges);

        $change = $historyChanges[0];
        self::assertEquals('status', $change->field);
        self::assertEquals(HistoryActionTypeEnum::UPDATE->value, $change->type);

        // Check enum value structure
        self::assertIsArray($change->from);
        self::assertEquals(TestStatusEnum::DRAFT->value, $change->from['value']);
        self::assertEquals('status.draft', $change->from['label']);
        self::assertEquals('enum', $change->from['type']);

        self::assertIsArray($change->to);
        self::assertEquals(TestStatusEnum::ACTIVE->value, $change->to['value']);
        self::assertEquals('status.active', $change->to['label']);
        self::assertEquals('enum', $change->to['type']);

        self::assertEquals('status.label', $change->translationKey);
    }

    public function testCreateFromUpdateWithNullEnumValues(): void
    {
        $factory = new TestHistoryFactory($this->entityManager, $this->translator);
        $user = new TestUser();

        $changes = [
            [
                'field' => 'status',
                'oldValue' => null,
                'newValue' => TestStatusEnum::ACTIVE->value,
                'actionType' => HistoryActionTypeEnum::UPDATE,
                'enumName' => 'status',
            ],
        ];

        $result = $factory->createFromUpdate($this->user, $user, $changes);

        $historyChanges = $result->getChanges();
        $change = $historyChanges[0];

        self::assertNull($change->from['value']);
        self::assertNull($change->from['label']);
        self::assertEquals('enum', $change->from['type']);
    }

    public function testCreateFromUpdateWithEntityRelationChanges(): void
    {
        $factory = new TestHistoryFactory($this->entityManager, $this->translator);
        $user = new TestUser();
        $createdBy = new TestUser(firstname: 'Admin', lastname: 'User', email: 'admin@example.com');

        $changes = [
            [
                'field' => 'createdBy',
                'oldValue' => null,
                'newValue' => [
                    'id' => $createdBy->getId()->toString(),
                    'label' => 'Admin User (admin@example.com)',
                ],
                'actionType' => HistoryActionTypeEnum::CHANGE_RELATION,
                'relatedEntity' => 'TestUser',
            ],
        ];

        $result = $factory->createFromUpdate($this->user, $user, $changes);

        $historyChanges = $result->getChanges();
        self::assertCount(1, $historyChanges);

        $change = $historyChanges[0];
        self::assertEquals('createdBy', $change->field);
        self::assertEquals(HistoryActionTypeEnum::CHANGE_RELATION->value, $change->type);
        self::assertNull($change->from);
        self::assertEquals([
            'id' => $createdBy->getId()->toString(),
            'label' => 'Admin User (admin@example.com)',
        ], $change->to);
        self::assertEquals('TestUser', $change->entityType);
    }

    public function testCreateFromUpdateWithMixedChanges(): void
    {
        $factory = new TestHistoryFactory($this->entityManager, $this->translator);
        $user = new TestUser();

        $changes = [
            [
                'field' => 'name',
                'oldValue' => 'old',
                'newValue' => 'new',
                'actionType' => HistoryActionTypeEnum::UPDATE,
            ],
            [
                'field' => 'status',
                'oldValue' => TestStatusEnum::DRAFT->value,
                'newValue' => TestStatusEnum::ACTIVE->value,
                'actionType' => HistoryActionTypeEnum::UPDATE,
                'enumName' => 'status',
            ],
        ];

        $result = $factory->createFromUpdate($this->user, $user, $changes);

        $historyChanges = $result->getChanges();
        self::assertCount(2, $historyChanges);

        // First change should be scalar
        self::assertEquals('name', $historyChanges[0]->field);
        self::assertEquals('old', $historyChanges[0]->from);
        self::assertEquals('new', $historyChanges[0]->to);

        // Second change should be enum
        self::assertEquals('status', $historyChanges[1]->field);
        self::assertIsArray($historyChanges[1]->from);
        self::assertIsArray($historyChanges[1]->to);
    }

    public function testCreateFromUpdateWithEmptyChanges(): void
    {
        $factory = new TestHistoryFactory($this->entityManager, $this->translator);
        $user = new TestUser();

        $changes = [];

        $this->entityManager->expects(self::once())->method('persist');

        $result = $factory->createFromUpdate($this->user, $user, $changes);

        $historyChanges = $result->getChanges();
        self::assertCount(0, $historyChanges);
    }
}

/**
 * Test implementation of BaseHistoryFactory for testing purposes.
 */
class TestHistoryFactory extends BaseHistoryFactory
{
    protected const string CLASS_NAME = TestUser::class;

    public function supports(IHistoryTrackable $entity): bool
    {
        return $entity instanceof TestUser;
    }

    /**
     * @param array<int, HistoryChange> $changes
     */
    protected function createHistoryEntity(
        ?UserInterface $user,
        ?string $message,
        HistorySeverityEnum $severity,
        array $changes,
        IHistoryTrackable $entity,
    ): IHistory {
        return new TestUserHistory(
            id: Uuid::uuid4(),
            createdBy: $user,
            message: $message,
            severity: $severity,
            changes: $changes,
            user: $entity,
        );
    }
}

/**
 * Test implementation without CLASS_NAME for testing error cases.
 */
class TestHistoryFactoryWithoutClassName extends BaseHistoryFactory
{
    // Intentionally not setting CLASS_NAME

    public function supports(IHistoryTrackable $entity): bool
    {
        return false;
    }

    protected function createHistoryEntity(
        ?UserInterface $user,
        ?string $message,
        HistorySeverityEnum $severity,
        array $changes,
        IHistoryTrackable $entity,
    ): IHistory {
        return new TestUserHistory(
            id: Uuid::uuid4(),
            createdBy: $user,
            message: $message,
            severity: $severity,
            changes: $changes,
            user: $entity,
        );
    }
}
