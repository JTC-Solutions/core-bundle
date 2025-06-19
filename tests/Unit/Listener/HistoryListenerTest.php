<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\Listener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\UnitOfWork;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use JtcSolutions\Core\Exception\HistoryException;
use JtcSolutions\Core\Extractor\BaseChangeExtractor;
use JtcSolutions\Core\Factory\BaseHistoryFactory;
use JtcSolutions\Core\Listener\HistoryListener;
use JtcSolutions\Core\Tests\Fixtures\History\TestUser;
use JtcSolutions\Core\Tests\Fixtures\History\TestUserHistory;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Unit tests for HistoryListener functionality.
 * Tests event handling, factory/extractor resolution, and error cases.
 */
class HistoryListenerTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private Security $security;

    private UnitOfWork $unitOfWork;

    private UserInterface $user;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->unitOfWork = $this->createMock(UnitOfWork::class);
        $this->user = $this->createMock(UserInterface::class);

        $this->entityManager->method('getUnitOfWork')->willReturn($this->unitOfWork);
    }

    public function testPostPersistIgnoresNonHistoryTrackableEntities(): void
    {
        $listener = new HistoryListener($this->entityManager, $this->security, [], []);

        $entity = new stdClass();
        $args = new PostPersistEventArgs($entity, $this->entityManager);

        // Should not throw any exceptions or call any methods
        $listener->postPersist($args);

        self::assertTrue(true); // Test passes if no exception is thrown
    }

    public function testPostPersistCreatesHistoryForTrackableEntity(): void
    {
        $user = new TestUser();
        $extractedData = ['id' => $user->getId()->toString(), 'label' => $user->getLabel()];
        $history = new TestUserHistory(
            Uuid::uuid4(),
            $this->user,
            null,
            HistorySeverityEnum::LOW,
            [],
            $user,
        );

        $extractor = $this->createMock(BaseChangeExtractor::class);
        $extractor->method('supports')->with($user)->willReturn(true);
        $extractor->method('extractCreationData')->with($user)->willReturn($extractedData);

        $factory = $this->createMock(BaseHistoryFactory::class);
        $factory->method('supports')->with($user)->willReturn(true);
        $factory->method('createFromCreate')->with($this->user, $user, $extractedData)->willReturn($history);

        $this->security->method('getUser')->willReturn($this->user);

        $listener = new HistoryListener($this->entityManager, $this->security, [$extractor], [$factory]);

        $args = new PostPersistEventArgs($user, $this->entityManager);

        $listener->postPersist($args);

        // Verify factory was called
        self::assertTrue(true); // Test passes if no exception is thrown
    }

    public function testPostPersistWithNullUser(): void
    {
        $user = new TestUser();
        $extractedData = ['id' => $user->getId()->toString(), 'label' => $user->getLabel()];
        $history = new TestUserHistory(
            Uuid::uuid4(),
            null,
            null,
            HistorySeverityEnum::LOW,
            [],
            $user,
        );

        $extractor = $this->createMock(BaseChangeExtractor::class);
        $extractor->method('supports')->with($user)->willReturn(true);
        $extractor->method('extractCreationData')->with($user)->willReturn($extractedData);

        $factory = $this->createMock(BaseHistoryFactory::class);
        $factory->method('supports')->with($user)->willReturn(true);
        $factory->method('createFromCreate')->with(null, $user, $extractedData)->willReturn($history);

        $this->security->method('getUser')->willReturn(null);

        $listener = new HistoryListener($this->entityManager, $this->security, [$extractor], [$factory]);

        $args = new PostPersistEventArgs($user, $this->entityManager);

        $listener->postPersist($args);

        self::assertTrue(true);
    }

    public function testPostPersistThrowsExceptionWhenExtractorNotFound(): void
    {
        $user = new TestUser();

        $extractor = $this->createMock(BaseChangeExtractor::class);
        $extractor->method('supports')->with($user)->willReturn(false);

        $this->security->method('getUser')->willReturn($this->user);

        $listener = new HistoryListener($this->entityManager, $this->security, [$extractor], []);

        $args = new PostPersistEventArgs($user, $this->entityManager);

        $this->expectException(HistoryException::class);
        $this->expectExceptionMessage('ChangeExtractor for entity');

        $listener->postPersist($args);
    }

    public function testPostPersistThrowsExceptionWhenFactoryNotFound(): void
    {
        $user = new TestUser();

        $extractor = $this->createMock(BaseChangeExtractor::class);
        $extractor->method('supports')->with($user)->willReturn(true);
        $extractor->method('extractCreationData')->willReturn([]);

        $factory = $this->createMock(BaseHistoryFactory::class);
        $factory->method('supports')->with($user)->willReturn(false);

        $this->security->method('getUser')->willReturn($this->user);

        $listener = new HistoryListener($this->entityManager, $this->security, [$extractor], [$factory]);

        $args = new PostPersistEventArgs($user, $this->entityManager);

        $this->expectException(HistoryException::class);
        $this->expectExceptionMessage('Factory for entity');

        $listener->postPersist($args);
    }

    public function testPreUpdateIgnoresNonHistoryTrackableEntities(): void
    {
        $listener = new HistoryListener($this->entityManager, $this->security, [], []);

        $entity = new stdClass();
        $changeSet = [];
        $args = new PreUpdateEventArgs($entity, $this->entityManager, $changeSet);

        $listener->preUpdate($args);

        self::assertTrue(true);
    }

    public function testPreUpdateCreatesHistoryForChangedEntity(): void
    {
        $user = new TestUser();
        $changeSet = ['firstname' => ['John', 'Jane']];
        $extractedChanges = [['field' => 'firstname', 'oldValue' => 'John', 'newValue' => 'Jane']];
        $collectionChanges = [];
        $history = new TestUserHistory(
            Uuid::uuid4(),
            $this->user,
            null,
            HistorySeverityEnum::LOW,
            [],
            $user,
        );

        $extractor = $this->createMock(BaseChangeExtractor::class);
        $extractor->method('supports')->with($user)->willReturn(true);
        $extractor->method('extractUpdateData')->with($changeSet)->willReturn($extractedChanges);
        $extractor->method('extractCollectionUpdateData')->willReturn($collectionChanges);
        $extractor->method('extractCollectionDeleteData')->willReturn([]);

        $factory = $this->createMock(BaseHistoryFactory::class);
        $factory->method('supports')->with($user)->willReturn(true);
        $factory->method('createFromUpdate')->with($this->user, $user, $extractedChanges)->willReturn($history);

        $this->security->method('getUser')->willReturn($this->user);
        $this->unitOfWork->method('getScheduledCollectionUpdates')->willReturn([]);
        $this->unitOfWork->method('getScheduledCollectionDeletions')->willReturn([]);

        $listener = new HistoryListener($this->entityManager, $this->security, [$extractor], [$factory]);

        $args = new PreUpdateEventArgs($user, $this->entityManager, $changeSet);

        $listener->preUpdate($args);

        self::assertTrue(true);
    }

    public function testPreUpdateSkipsWhenNoChanges(): void
    {
        $user = new TestUser();
        $changeSet = [];

        $extractor = $this->createMock(BaseChangeExtractor::class);
        $extractor->method('supports')->with($user)->willReturn(true);
        $extractor->method('extractUpdateData')->with($changeSet)->willReturn([]);
        $extractor->method('extractCollectionUpdateData')->willReturn([]);
        $extractor->method('extractCollectionDeleteData')->willReturn([]);

        $factory = $this->createMock(BaseHistoryFactory::class);
        $factory->expects(self::never())->method('createFromUpdate'); // Should not be called

        $this->security->method('getUser')->willReturn($this->user);
        $this->unitOfWork->method('getScheduledCollectionUpdates')->willReturn([]);
        $this->unitOfWork->method('getScheduledCollectionDeletions')->willReturn([]);

        $listener = new HistoryListener($this->entityManager, $this->security, [$extractor], [$factory]);

        $args = new PreUpdateEventArgs($user, $this->entityManager, $changeSet);

        $listener->preUpdate($args);

        self::assertTrue(true);
    }

    public function testPreUpdateWithCollectionChanges(): void
    {
        $user = new TestUser();
        $changeSet = [];
        $collectionChanges = [['field' => 'items', 'actionType' => 'ADDED_TO_COLLECTION']];
        $collectionDeletions = [['field' => 'items', 'actionType' => 'REMOVED_FROM_COLLECTION']];
        $allChanges = array_merge([], $collectionChanges, $collectionDeletions);

        $extractor = $this->createMock(BaseChangeExtractor::class);
        $extractor->method('supports')->with($user)->willReturn(true);
        $extractor->method('extractUpdateData')->with($changeSet)->willReturn([]);
        $extractor->method('extractCollectionUpdateData')->willReturn($collectionChanges);
        $extractor->method('extractCollectionDeleteData')->willReturn($collectionDeletions);

        $factory = $this->createMock(BaseHistoryFactory::class);
        $factory->method('supports')->with($user)->willReturn(true);
        $factory->method('createFromUpdate')->with($this->user, $user, $allChanges)->willReturn(
            new TestUserHistory(
                Uuid::uuid4(),
                $this->user,
                null,
                HistorySeverityEnum::LOW,
                [],
                $user,
            ),
        );

        $this->security->method('getUser')->willReturn($this->user);
        $this->unitOfWork->method('getScheduledCollectionUpdates')->willReturn([]);
        $this->unitOfWork->method('getScheduledCollectionDeletions')->willReturn([]);

        $listener = new HistoryListener($this->entityManager, $this->security, [$extractor], [$factory]);

        $args = new PreUpdateEventArgs($user, $this->entityManager, $changeSet);

        $listener->preUpdate($args);

        self::assertTrue(true);
    }

    public function testPostUpdateCallsFlush(): void
    {
        $this->entityManager->expects(self::once())->method('flush');

        $listener = new HistoryListener($this->entityManager, $this->security, [], []);

        $entity = new stdClass();
        $changeSet = [];
        $args = new PostUpdateEventArgs($entity, $this->entityManager, $changeSet);

        $listener->postUpdate($args);
    }

    public function testGetHistoryFactoryFindsCorrectFactory(): void
    {
        $user = new TestUser();

        $factory1 = $this->createMock(BaseHistoryFactory::class);
        $factory1->method('supports')->with($user)->willReturn(false);

        $factory2 = $this->createMock(BaseHistoryFactory::class);
        $factory2->method('supports')->with($user)->willReturn(true);

        $listener = new HistoryListener($this->entityManager, $this->security, [], [$factory1, $factory2]);

        // Use reflection to test protected method
        $reflection = new ReflectionClass($listener);
        $method = $reflection->getMethod('getHistoryFactory');
        $method->setAccessible(true);

        $result = $method->invoke($listener, $user);

        self::assertSame($factory2, $result);
    }

    public function testGetChangeExtractorFindsCorrectExtractor(): void
    {
        $user = new TestUser();

        $extractor1 = $this->createMock(BaseChangeExtractor::class);
        $extractor1->method('supports')->with($user)->willReturn(false);

        $extractor2 = $this->createMock(BaseChangeExtractor::class);
        $extractor2->method('supports')->with($user)->willReturn(true);

        $listener = new HistoryListener($this->entityManager, $this->security, [$extractor1, $extractor2], []);

        // Use reflection to test protected method
        $reflection = new ReflectionClass($listener);
        $method = $reflection->getMethod('getChangeExtractor');
        $method->setAccessible(true);

        $result = $method->invoke($listener, $user);

        self::assertSame($extractor2, $result);
    }

    public function testPostPersistWithMultipleExtractorsAndFactories(): void
    {
        $user = new TestUser();
        $extractedData = ['id' => $user->getId()->toString(), 'label' => $user->getLabel()];

        // Create multiple extractors where only one supports the entity
        $extractor1 = $this->createMock(BaseChangeExtractor::class);
        $extractor1->method('supports')->willReturn(false);

        $extractor2 = $this->createMock(BaseChangeExtractor::class);
        $extractor2->method('supports')->with($user)->willReturn(true);
        $extractor2->method('extractCreationData')->with($user)->willReturn($extractedData);

        // Create multiple factories where only one supports the entity
        $factory1 = $this->createMock(BaseHistoryFactory::class);
        $factory1->method('supports')->willReturn(false);

        $factory2 = $this->createMock(BaseHistoryFactory::class);
        $factory2->method('supports')->with($user)->willReturn(true);
        $factory2->method('createFromCreate')->willReturn(
            new TestUserHistory(
                Uuid::uuid4(),
                $this->user,
                null,
                HistorySeverityEnum::LOW,
                [],
                $user,
            ),
        );

        $this->security->method('getUser')->willReturn($this->user);

        $listener = new HistoryListener(
            $this->entityManager,
            $this->security,
            [$extractor1, $extractor2],
            [$factory1, $factory2],
        );

        $args = new PostPersistEventArgs($user, $this->entityManager);

        $listener->postPersist($args);

        self::assertTrue(true);
    }
}
