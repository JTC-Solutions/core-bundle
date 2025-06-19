<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\Listener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use JtcSolutions\Core\Enum\HistoryActionTypeEnum;
use JtcSolutions\Core\Listener\HistoryListener;
use JtcSolutions\Core\Tests\Fixtures\History\TestPivotChangeExtractor;
use JtcSolutions\Core\Tests\Fixtures\History\TestPivotDoctrineEventParser;
use JtcSolutions\Core\Tests\Fixtures\History\TestPivotEntity;
use JtcSolutions\Core\Tests\Fixtures\History\TestPivotHistoryFactory;
use JtcSolutions\Core\Tests\Fixtures\History\TestRole;
use JtcSolutions\Core\Tests\Fixtures\History\TestRoleChangeExtractor;
use JtcSolutions\Core\Tests\Fixtures\History\TestUser;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class HistoryListenerPivotTest extends TestCase
{
    private HistoryListener $listener;

    private EntityManagerInterface $entityManager;

    private Security $security;

    private TestPivotChangeExtractor $userChangeExtractor;

    private TestRoleChangeExtractor $roleChangeExtractor;

    private TestPivotHistoryFactory $historyFactory;

    /** @var TestPivotHistoryFactory&MockObject */
    private TestPivotHistoryFactory $historyFactoryMock;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);

        $parser = new TestPivotDoctrineEventParser();
        $this->userChangeExtractor = new TestPivotChangeExtractor($parser);
        $this->roleChangeExtractor = new TestRoleChangeExtractor($parser);
        $this->historyFactory = new TestPivotHistoryFactory($this->entityManager, $this->createMock(TranslatorInterface::class));
        $this->historyFactoryMock = $this->createMock(TestPivotHistoryFactory::class);

        $this->listener = new HistoryListener(
            $this->entityManager,
            $this->security,
            [$this->userChangeExtractor, $this->roleChangeExtractor],
            [$this->historyFactoryMock],
        );
    }

    public function testPostPersistHandlesPivotEntity(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $role = new TestRole('admin', 'Administrator role');
        $pivotEntity = new TestPivotEntity($user, $role, ['read', 'write']);

        $securityUser = $this->createMock(UserInterface::class);
        $this->security->expects(self::once())
            ->method('getUser')
            ->willReturn($securityUser);

        // Mock factory supports both User and Role entities (dual history)
        $this->historyFactoryMock->expects(self::atLeastOnce())
            ->method('supports')
            ->willReturnCallback(static fn ($entity) => $entity === $user || $entity === $role);

        // Expect createFromUpdate for dual history (at least once, possibly twice)
        $this->historyFactoryMock->expects(self::atLeastOnce())
            ->method('createFromUpdate')
            ->willReturnCallback(function ($secUser, $entity, $changes) use ($securityUser, $user, $role) {
                $this->assertSame($securityUser, $secUser);
                $this->assertCount(1, $changes);
                $change = $changes[0];
                $this->assertSame(HistoryActionTypeEnum::PIVOT_CREATED, $change['actionType']);
                $this->assertSame('TestPivotEntity', $change['pivotEntity']);

                if ($entity === $user) {
                    // Owner perspective: user gets role
                    $this->assertSame('role', $change['field']);
                    $this->assertArrayHasKey('id', $change['newValue']);
                    $this->assertSame('TestRole', $change['relatedEntity']);
                } elseif ($entity === $role) {
                    // Target perspective: role gets user
                    $this->assertSame('user', $change['field']); // Reverse relationship type
                    $this->assertArrayHasKey('id', $change['newValue']);
                    $this->assertSame('TestUser', $change['relatedEntity']);
                } else {
                    $this->fail('Unexpected entity type in createFromUpdate: ' . $entity::class);
                }

                return true;
            });

        $args = new PostPersistEventArgs($pivotEntity, $this->entityManager);

        $this->listener->postPersist($args);
    }

    public function testPreUpdateHandlesPivotEntity(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $role = new TestRole('admin', 'Administrator role');
        $pivotEntity = new TestPivotEntity($user, $role, ['read']);

        $changeSet = [
            'permissions' => [['read'], ['read', 'write', 'delete']],
        ];

        $securityUser = $this->createMock(UserInterface::class);
        $this->security->expects(self::once())
            ->method('getUser')
            ->willReturn($securityUser);

        $this->historyFactoryMock->expects(self::atLeastOnce())
            ->method('supports')
            ->willReturnCallback(static fn ($entity) => $entity === $user || $entity === $role);

        $this->historyFactoryMock->expects(self::atLeastOnce())
            ->method('createFromUpdate')
            ->willReturnCallback(function ($secUser, $entity, $changes) use ($securityUser) {
                $this->assertSame($securityUser, $secUser);
                $this->assertCount(1, $changes);
                $change = $changes[0];
                $this->assertSame(HistoryActionTypeEnum::PIVOT_UPDATED, $change['actionType']);
                $this->assertSame('TestPivotEntity', $change['pivotEntity']);
                return true;
            });

        $args = new PreUpdateEventArgs($pivotEntity, $this->entityManager, $changeSet);

        $this->listener->preUpdate($args);
    }

    public function testPreRemoveHandlesPivotEntity(): void
    {
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $role = new TestRole('admin', 'Administrator role');
        $pivotEntity = new TestPivotEntity($user, $role, ['read', 'write', 'delete']);

        $securityUser = $this->createMock(UserInterface::class);
        $this->security->expects(self::once())
            ->method('getUser')
            ->willReturn($securityUser);

        $this->historyFactoryMock->expects(self::atLeastOnce())
            ->method('supports')
            ->willReturnCallback(static fn ($entity) => $entity === $user || $entity === $role);

        $this->historyFactoryMock->expects(self::atLeastOnce())
            ->method('createFromUpdate')
            ->willReturnCallback(function ($secUser, $entity, $changes) use ($securityUser) {
                $this->assertSame($securityUser, $secUser);
                $this->assertCount(1, $changes);
                $change = $changes[0];
                $this->assertSame(HistoryActionTypeEnum::PIVOT_DELETED, $change['actionType']);
                $this->assertSame('TestPivotEntity', $change['pivotEntity']);
                return true;
            });

        $args = new PreRemoveEventArgs($pivotEntity, $this->entityManager);

        $this->listener->preRemove($args);
    }

    public function testPostPersistIgnoresNonPivotEntities(): void
    {
        $regularEntity = new stdClass();

        $this->security->expects(self::never())
            ->method('getUser');

        $this->historyFactoryMock->expects(self::never())
            ->method('createFromUpdate');

        $args = new PostPersistEventArgs($regularEntity, $this->entityManager);

        $this->listener->postPersist($args);
    }

    public function testPreRemoveIgnoresNonPivotEntities(): void
    {
        $regularEntity = new stdClass();

        $this->security->expects(self::never())
            ->method('getUser');

        $this->historyFactoryMock->expects(self::never())
            ->method('createFromUpdate');

        $args = new PreRemoveEventArgs($regularEntity, $this->entityManager);

        $this->listener->preRemove($args);
    }

    public function testHandlePivotEntityChangeWithUnsupportedOwner(): void
    {
        // Create a pivot entity where the owner is trackable but no factory supports it
        $user = new TestUser(null, 'testuser', 'Doe', 'test@example.com');
        $role = new TestRole('admin', 'Administrator role');
        $pivotEntity = new TestPivotEntity($user, $role, ['read', 'write']);

        $securityUser = $this->createMock(UserInterface::class);
        $this->security->expects(self::once())
            ->method('getUser')
            ->willReturn($securityUser);

        // Mock factory that doesn't support this entity
        $this->historyFactoryMock->expects(self::atLeastOnce())
            ->method('supports')
            ->willReturnCallback(static fn ($entity) => false);

        $this->historyFactoryMock->expects(self::never())
            ->method('createFromUpdate');

        $args = new PostPersistEventArgs($pivotEntity, $this->entityManager);

        // This should not throw exception - HistoryListener catches and logs errors
        // but doesn't break normal application flow
        $this->listener->postPersist($args);

        // Test passes if no exception is thrown and createFromUpdate was not called
        self::assertTrue(true);
    }
}
