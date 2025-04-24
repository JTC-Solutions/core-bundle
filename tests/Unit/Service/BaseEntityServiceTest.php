<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\Service;

use Doctrine\ORM\EntityNotFoundException as DoctrineEntityNotFoundException;
use JtcSolutions\Core\Dto\EntityId;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Exception\EntityAlreadyExistsException;
use JtcSolutions\Core\Exception\EntityNotFoundException;
use JtcSolutions\Core\Exception\NestedEntityNotFoundException;
use JtcSolutions\Core\Repository\IEntityRepository;
use JtcSolutions\Core\Tests\Fixtures\Concrete\ConcreteTestCRUDEntityService;
use JtcSolutions\Core\Tests\Fixtures\Concrete\Dto\TestRequestBody;
use JtcSolutions\Core\Tests\Fixtures\Concrete\Entity\TestEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class BaseEntityServiceTest extends TestCase
{
    // Mocks for dependencies injected into the service
    private MockObject|IEntityRepository $repositoryMock;

    private MockObject|LoggerInterface $loggerMock;

    // The concrete service instance used for testing base logic (ensure*, find*, update*)
    private ConcreteTestCRUDEntityService $serviceInstance;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->repositoryMock = $this->createMock(IEntityRepository::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        // Configure the main repository mock to always return the concrete TestEntity class name
        $this->repositoryMock->method('getEntityName')->willReturn(TestEntity::class);

        // Create a real instance of the concrete service to test the base logic
        // This instance will have its dependencies mocked
        $this->serviceInstance = new ConcreteTestCRUDEntityService(
            $this->repositoryMock,
            $this->loggerMock,
        );
    }

    // --- Tests for handleCreate / handleUpdate / handleDelete Delegation ---
    // These tests verify that the public handle* methods in BaseEntityService
    // correctly call the corresponding protected abstract methods.
    // We mock the *concrete* service here to isolate this interaction.

    public function testHandleCreateDelegatesToMapDataAndCallCreate(): void
    {
        $requestBody = new TestRequestBody('create data');
        $expectedEntity = new TestEntity(Uuid::uuid4()); // Dummy entity to be returned by mocked method

        // Create a mock of the *concrete* service, but *only* mock the protected method
        $serviceMock = $this->getMockBuilder(ConcreteTestCRUDEntityService::class)
            ->setConstructorArgs([$this->repositoryMock, $this->loggerMock])
            ->onlyMethods(['mapDataAndCallCreate']) // Mock only this protected method
            ->getMock();

        // Expect 'mapDataAndCallCreate' to be called once with the request body
        $serviceMock->expects(self::once())
            ->method('mapDataAndCallCreate')
            ->with($requestBody)
            ->willReturn($expectedEntity); // Define return value for the mocked method

        // Call the public handleCreate method on the mocked service
        $result = $serviceMock->handleCreate($requestBody);

        // Assert that the result returned by handleCreate is the one from the mocked method
        self::assertSame($expectedEntity, $result);
    }

    public function testHandleUpdateDelegatesToMapDataAndCallUpdate(): void
    {
        $entityId = Uuid::uuid4();
        $requestBody = new TestRequestBody('update data');
        $expectedEntity = new TestEntity($entityId); // Dummy entity to be returned

        // Create a mock of the *concrete* service, mocking only the protected method
        $serviceMock = $this->getMockBuilder(ConcreteTestCRUDEntityService::class)
            ->setConstructorArgs([$this->repositoryMock, $this->loggerMock])
            ->onlyMethods(['mapDataAndCallUpdate']) // Mock only this protected method
            ->getMock();

        // Expect 'mapDataAndCallUpdate' to be called once with the correct ID and body
        // Note: BaseEntityService calls mapDataAndCallUpdate with UuidInterface,
        // so we expect UuidInterface here, even if the concrete implementation allows IEntity.
        $serviceMock->expects(self::once())
            ->method('mapDataAndCallUpdate')
            ->with($entityId, $requestBody) // Expecting UuidInterface and RequestBody
            ->willReturn($expectedEntity);

        // Call the public handleUpdate method
        $result = $serviceMock->handleUpdate($entityId, $requestBody);

        // Assert the result
        self::assertSame($expectedEntity, $result);
    }

    public function testHandleDeleteDelegatesToDeleteWithUuid(): void
    {
        $entityId = Uuid::uuid4();

        // Create a mock of the *concrete* service, mocking only the protected method
        $serviceMock = $this->getMockBuilder(ConcreteTestCRUDEntityService::class)
            ->setConstructorArgs([$this->repositoryMock, $this->loggerMock])
            ->onlyMethods(['delete']) // Mock only this protected method
            ->getMock();

        // Expect 'delete' to be called once with the UUID
        $serviceMock->expects(self::once())
            ->method('delete')
            ->with($entityId);

        // Call the public handleDelete method
        $serviceMock->handleDelete($entityId);
    }

    public function testHandleDeleteDelegatesToDeleteWithEntity(): void
    {
        $entityId = Uuid::uuid4();
        $entity = new TestEntity($entityId);

        // Create a mock of the *concrete* service, mocking only the protected method
        $serviceMock = $this->getMockBuilder(ConcreteTestCRUDEntityService::class)
            ->setConstructorArgs([$this->repositoryMock, $this->loggerMock])
            ->onlyMethods(['delete']) // Mock only this protected method
            ->getMock();

        // Expect 'delete' to be called once with the Entity object
        $serviceMock->expects(self::once())
            ->method('delete')
            ->with($entity);

        // Call the public handleDelete method
        $serviceMock->handleDelete($entity);
    }


    // --- Tests for BaseEntityService Logic (ensure*, find*, update*) ---
    // These tests use the *real* concrete service instance ($this->serviceInstance)
    // created in setUp() and call the public wrapper methods to test the
    // logic implemented directly within BaseEntityService.

    // --- Tests for ensureEntityDoesNotExist ---

    public function testEnsureEntityDoesNotExistSucceedsWhenRepoThrowsNotFound(): void
    {
        $params = ['field' => 'value'];

        // Configure the main repository mock (used by $this->serviceInstance)
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with($params)
            ->willThrowException(new DoctrineEntityNotFoundException());

        // Call the public wrapper on the real service instance
        // No exception should be thrown
        $this->serviceInstance->publicEnsureEntityDoesNotExist($params);
        self::assertTrue(true); // Assertion to indicate test passed without exception
    }

    public function testEnsureEntityDoesNotExistThrowsWhenRepoFindsEntity(): void
    {
        $params = ['field' => 'value'];
        $existingEntity = new TestEntity(Uuid::uuid4());

        // Configure the main repository mock
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with($params)
            ->willReturn($existingEntity);

        // Expect the specific exception from the base service logic
        $this->expectException(EntityAlreadyExistsException::class);
        $this->expectExceptionMessage(sprintf(
            'Entity %s already exists, you can not create duplicate. It was looked for by params: %s',
            TestEntity::class, // Uses the class name from the mocked repository
            json_encode($params),
        ));

        // Call the public wrapper on the real service instance
        $this->serviceInstance->publicEnsureEntityDoesNotExist($params);
    }

    public function testEnsureEntityDoesNotExistSucceedsWhenRepoFindsIgnoredEntity(): void
    {
        $params = ['field' => 'value'];
        $ignoredEntityId = Uuid::uuid4();
        $existingEntity = new TestEntity($ignoredEntityId); // Entity with the ID to ignore

        // Configure the main repository mock
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with($params)
            ->willReturn($existingEntity);

        // Call the public wrapper on the real service instance
        // No exception should be thrown
        $this->serviceInstance->publicEnsureEntityDoesNotExist($params, $ignoredEntityId);
        self::assertTrue(true); // Assertion to indicate test passed
    }

    public function testEnsureEntityDoesNotExistThrowsWhenRepoFindsDifferentEntityThanIgnored(): void
    {
        $params = ['field' => 'value'];
        $ignoredEntityId = Uuid::uuid4();
        $existingEntityId = Uuid::uuid4();
        $existingEntity = new TestEntity($existingEntityId); // Different ID

        // Configure the main repository mock
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with($params)
            ->willReturn($existingEntity);

        // Expect the specific exception
        $this->expectException(EntityAlreadyExistsException::class);
        $this->expectExceptionMessage(sprintf(
            'Entity %s already exists, you can not create duplicate. It was looked for by params: %s, with duplicity ignoring entity %s',
            TestEntity::class, // Uses the class name from the mocked repository
            json_encode($params),
            $ignoredEntityId->toString(),
        ));

        // Call the public wrapper on the real service instance
        $this->serviceInstance->publicEnsureEntityDoesNotExist($params, $ignoredEntityId);
    }

    // --- Tests for ensureEntityExists ---

    public function testEnsureEntityExistsReturnsEntityWhenFound(): void
    {
        $params = ['id' => Uuid::uuid4()];
        $expectedEntity = new TestEntity($params['id']);

        // Configure the main repository mock
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with($params)
            ->willReturn($expectedEntity);

        // Call the public wrapper on the real service instance
        $result = $this->serviceInstance->publicEnsureEntityExists($params);

        self::assertSame($expectedEntity, $result);
    }

    public function testEnsureEntityExistsThrowsCustomExceptionWhenNotFound(): void
    {
        $params = ['id' => Uuid::uuid4()];
        $doctrineException = new DoctrineEntityNotFoundException();

        // Configure the main repository mock
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with($params)
            ->willThrowException($doctrineException);

        // Expect the specific exception from the base service logic
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage(sprintf(
            'Entity %s was not found by params %s',
            TestEntity::class, // Uses the class name from the mocked repository
            json_encode($params),
        ));

        try {
            // Call the public wrapper on the real service instance
            $this->serviceInstance->publicEnsureEntityExists($params);
        } catch (EntityNotFoundException $e) {
            // Assert that the original Doctrine exception is wrapped
            self::assertSame($doctrineException, $e->getPrevious());
            throw $e; // Re-throw to satisfy PHPUnit's expectException
        }
    }

    // --- Tests for findEntityById ---

    public function testFindEntityByIdReturnsEntityWhenFound(): void
    {
        $entityId = Uuid::uuid4();
        $expectedEntity = new TestEntity($entityId);

        // Mock a *secondary* repository (not the one injected in setUp)
        // This repository will be passed directly to the method call
        $secondaryRepositoryMock = $this->createMock(IEntityRepository::class);
        $secondaryRepositoryMock->method('getEntityName')->willReturn('SecondaryTestEntity');
        $secondaryRepositoryMock->expects(self::once())
            ->method('find')
            ->with($entityId)
            ->willReturn($expectedEntity);

        // Call the public wrapper on the real service instance, passing the secondary mock
        $result = $this->serviceInstance->publicFindEntityById($entityId, $secondaryRepositoryMock);

        self::assertSame($expectedEntity, $result);
    }

    public function testFindEntityByIdThrowsNestedExceptionWhenNotFound(): void
    {
        $entityId = Uuid::uuid4();
        $doctrineException = new DoctrineEntityNotFoundException();

        // Mock a *secondary* repository
        $secondaryRepositoryMock = $this->createMock(IEntityRepository::class);
        $secondaryRepositoryMock->method('getEntityName')->willReturn('SecondaryTestEntity');
        $secondaryRepositoryMock->expects(self::once())
            ->method('find')
            ->with($entityId)
            ->willThrowException($doctrineException);

        // Expect the specific exception from the base service logic
        $this->expectException(NestedEntityNotFoundException::class);
        $this->expectExceptionMessage(sprintf(
            'Nested entity %s with id %s was not found for parent entity %s.',
            'SecondaryTestEntity', // Name from the secondary mock
            $entityId->toString(),
            TestEntity::class, // Parent entity type comes from the main repository mock (via $this->serviceInstance)
        ));

        try {
            // Call the public wrapper on the real service instance
            $this->serviceInstance->publicFindEntityById($entityId, $secondaryRepositoryMock);
        } catch (NestedEntityNotFoundException $e) {
            // Assert that the original Doctrine exception is wrapped
            self::assertSame($doctrineException, $e->getPrevious());
            throw $e; // Re-throw
        }
    }

    // --- Tests for updateCollection ---

    public function testUpdateCollectionAddsNewItems(): void
    {
        $existingEntities = []; // Start with no existing entities
        $newId1 = Uuid::uuid4();
        $newId2 = Uuid::uuid4();
        // Inputs are DTOs with IDs
        $inputs = [
            new EntityId($newId1),
            new EntityId($newId2),
        ];

        // Variables to capture calls to the callbacks
        $addedIds = [];
        $removedEntities = [];

        // Define the callbacks that updateCollection will use
        $addEntityCallable = static function (UuidInterface $id) use (&$addedIds): void {
            $addedIds[] = $id->toString();
        };
        $removeEntityCallable = static function (IEntity $entity) use (&$removedEntities): void {
            $removedEntities[] = $entity->getId()->toString();
        };

        // Call the public wrapper on the real service instance
        $this->serviceInstance->publicUpdateCollection($existingEntities, $inputs, $addEntityCallable, $removeEntityCallable);

        // Assertions: Check which IDs were passed to the add callback
        self::assertCount(2, $addedIds);
        self::assertContains($newId1->toString(), $addedIds);
        self::assertContains($newId2->toString(), $addedIds);
        // Assertions: Check that no entities were passed to the remove callback
        self::assertCount(0, $removedEntities);
    }

    public function testUpdateCollectionRemovesMissingItems(): void
    {
        $existingId1 = Uuid::uuid4();
        $existingId2 = Uuid::uuid4();
        // Start with two existing entities
        $existingEntities = [
            new TestEntity($existingId1),
            new TestEntity($existingId2),
        ];
        $inputs = []; // No inputs means all existing entities should be removed

        $addedIds = [];
        $removedEntities = [];

        $addEntityCallable = static function (UuidInterface $id) use (&$addedIds): void {
            $addedIds[] = $id->toString();
        };
        $removeEntityCallable = static function (IEntity $entity) use (&$removedEntities): void {
            $removedEntities[] = $entity->getId()->toString();
        };

        // Call the public wrapper on the real service instance
        $this->serviceInstance->publicUpdateCollection($existingEntities, $inputs, $addEntityCallable, $removeEntityCallable);

        // Assertions: No entities should be added
        self::assertCount(0, $addedIds);
        // Assertions: Both existing entities should be removed
        self::assertCount(2, $removedEntities);
        self::assertContains($existingId1->toString(), $removedEntities);
        self::assertContains($existingId2->toString(), $removedEntities);
    }

    public function testUpdateCollectionAddsAndRemovesItems(): void
    {
        $keepId = Uuid::uuid4();
        $removeId = Uuid::uuid4();
        // Start with two existing entities
        $existingEntities = [
            new TestEntity($keepId),
            new TestEntity($removeId),
        ];

        $addId = Uuid::uuid4();
        // Inputs: Keep one existing, add a new one. The other existing one is implicitly removed.
        $inputs = [
            new EntityId($keepId), // Keep this one
            new EntityId($addId), // Add this one
            // $removeId is missing from inputs
        ];

        $addedIds = [];
        $removedEntities = [];

        $addEntityCallable = static function (UuidInterface $id) use (&$addedIds): void {
            $addedIds[] = $id->toString();
        };
        $removeEntityCallable = static function (IEntity $entity) use (&$removedEntities): void {
            $removedEntities[] = $entity->getId()->toString();
        };

        // Call the public wrapper on the real service instance
        $this->serviceInstance->publicUpdateCollection($existingEntities, $inputs, $addEntityCallable, $removeEntityCallable);

        // Assertions: One entity should be added
        self::assertCount(1, $addedIds);
        self::assertContains($addId->toString(), $addedIds);
        // Assertions: One entity should be removed
        self::assertCount(1, $removedEntities);
        self::assertContains($removeId->toString(), $removedEntities);
    }

    public function testUpdateCollectionWithEntityInputs(): void
    {
        $keepId = Uuid::uuid4();
        $removeId = Uuid::uuid4();
        // Start with two existing entities
        $existingEntities = [
            new TestEntity($keepId),
            new TestEntity($removeId),
        ];

        $addId = Uuid::uuid4();
        // Inputs: Keep one existing, add a new one. Inputs are actual Entity objects.
        $inputs = [
            new TestEntity($keepId), // Keep this one (passed as Entity)
            new TestEntity($addId), // Add this one (passed as Entity)
            // $removeId is missing from inputs
        ];

        $addedIds = [];
        $removedEntities = [];

        $addEntityCallable = static function (UuidInterface $id) use (&$addedIds): void {
            $addedIds[] = $id->toString();
        };
        $removeEntityCallable = static function (IEntity $entity) use (&$removedEntities): void {
            $removedEntities[] = $entity->getId()->toString();
        };

        // Call the public wrapper on the real service instance
        $this->serviceInstance->publicUpdateCollection($existingEntities, $inputs, $addEntityCallable, $removeEntityCallable);

        // Assertions: One entity should be added
        self::assertCount(1, $addedIds);
        self::assertContains($addId->toString(), $addedIds);
        // Assertions: One entity should be removed
        self::assertCount(1, $removedEntities);
        self::assertContains($removeId->toString(), $removedEntities);
    }
}
