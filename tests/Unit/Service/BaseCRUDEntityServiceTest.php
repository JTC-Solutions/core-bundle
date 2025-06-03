<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Dto\DummyCreateRequest;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Entity\DummyEntity;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Repository\DummyRepository;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Service\DummyCRUDEntityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class BaseCRUDEntityServiceTest extends TestCase
{
    private MockObject|DummyRepository $repositoryMock;

    private MockObject|LoggerInterface $loggerMock;

    private MockObject|EntityManagerInterface $entityManagerMock;

    private DummyCRUDEntityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryMock = $this->createMock(DummyRepository::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->service = new DummyCRUDEntityService(
            $this->repositoryMock,
            $this->entityManagerMock,
        );

        // Explicitly set the logger since it's not set in the constructor but via #[Required] attribute
        $this->service->setLogger($this->loggerMock);
    }

    public function testHandleCreatePassesContextToMapDataAndCallCreate(): void
    {
        // Arrange
        $requestBody = new DummyCreateRequest('test string', 123, 45.67);
        $context = [
            'log_message' => 'Creating entity with context',
            'contextString' => 'context value for create',
        ];

        // DummyCRUDEntityService doesn't use the logger, so we don't expect it to be called

        // The entity manager should persist and flush the entity
        $this->entityManagerMock->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(DummyEntity::class));
        $this->entityManagerMock->expects(self::once())
            ->method('flush');

        // Act
        $result = $this->service->handleCreate($requestBody, $context);

        // Assert
        self::assertInstanceOf(DummyEntity::class, $result);
        self::assertEquals('test string', $result->getString());
        self::assertEquals(123, $result->getInteger());
        self::assertEquals(45.67, $result->getFloat());
        self::assertEquals('context value for create', $result->getContextString());
    }

    public function testHandleUpdatePassesContextToMapDataAndCallUpdate(): void
    {
        // Arrange
        $entityId = Uuid::uuid4();
        $entity = new DummyEntity($entityId, 'old string', 456, 78.9, 'old context');
        $requestBody = new DummyCreateRequest('updated string', 789, 12.34);
        $context = [
            'log_message' => 'Updating entity with context',
            'contextString' => 'context value for update',
        ];

        // Configure repository to return the entity when findOneBy is called
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with(['id' => $entityId])
            ->willReturn($entity);

        // DummyCRUDEntityService doesn't use the logger, so we don't expect it to be called

        // The entity manager should flush the changes
        $this->entityManagerMock->expects(self::once())
            ->method('flush');

        // Act
        $result = $this->service->handleUpdate($entityId, $requestBody, $context);

        // Assert
        self::assertInstanceOf(DummyEntity::class, $result);
        self::assertEquals('updated string', $result->getString());
        self::assertEquals(789, $result->getInteger());
        self::assertEquals(12.34, $result->getFloat());
        self::assertEquals('context value for update', $result->getContextString());
    }

    public function testHandleDeletePassesContextToDelete(): void
    {
        // Arrange
        $entityId = Uuid::uuid4();
        $entity = new DummyEntity($entityId, 'test string', 123, 45.67, 'context for delete');
        $context = [
            'log_message' => 'Deleting entity with context',
            'contextString' => 'context value for delete',
        ];

        // Configure repository to return the entity when findOneBy is called
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with(['id' => $entityId])
            ->willReturn($entity);

        // DummyCRUDEntityService doesn't use the logger, so we don't expect it to be called

        // The entity manager should remove and flush the entity
        $this->entityManagerMock->expects(self::once())
            ->method('remove')
            ->with($entity);
        $this->entityManagerMock->expects(self::once())
            ->method('flush');

        // Act
        $this->service->handleDelete($entityId, $context);
    }

    public function testHandleCreateWithoutContext(): void
    {
        // Arrange
        $requestBody = new DummyCreateRequest('test string', 123, 45.67);
        // Even without a log_message, we still need to provide contextString
        $context = ['contextString' => 'default context for create'];

        // DummyCRUDEntityService doesn't use the logger, so we don't need to check if it's called

        // The entity manager should persist and flush the entity
        $this->entityManagerMock->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(DummyEntity::class));
        $this->entityManagerMock->expects(self::once())
            ->method('flush');

        // Act
        $result = $this->service->handleCreate($requestBody, $context);

        // Assert
        self::assertInstanceOf(DummyEntity::class, $result);
        self::assertEquals('test string', $result->getString());
        self::assertEquals(123, $result->getInteger());
        self::assertEquals(45.67, $result->getFloat());
        self::assertEquals('default context for create', $result->getContextString());
    }

    public function testHandleUpdateWithoutContext(): void
    {
        // Arrange
        $entityId = Uuid::uuid4();
        $entity = new DummyEntity($entityId, 'old string', 456, 78.9, 'old context for update');
        $requestBody = new DummyCreateRequest('updated string', 789, 12.34);
        // Even without a log_message, we still need to provide contextString
        $context = ['contextString' => 'default context for update'];

        // Configure repository to return the entity when findOneBy is called
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with(['id' => $entityId])
            ->willReturn($entity);

        // DummyCRUDEntityService doesn't use the logger, so we don't need to check if it's called

        // The entity manager should flush the changes
        $this->entityManagerMock->expects(self::once())
            ->method('flush');

        // Act
        $result = $this->service->handleUpdate($entityId, $requestBody, $context);

        // Assert
        self::assertInstanceOf(DummyEntity::class, $result);
        self::assertEquals('updated string', $result->getString());
        self::assertEquals(789, $result->getInteger());
        self::assertEquals(12.34, $result->getFloat());
        self::assertEquals('default context for update', $result->getContextString());
    }

    public function testHandleDeleteWithoutContext(): void
    {
        // Arrange
        $entityId = Uuid::uuid4();
        $entity = new DummyEntity($entityId, 'test string', 123, 45.67, 'context for delete without log');
        // Even without a log_message, we still need to provide contextString
        $context = ['contextString' => 'default context for delete'];

        // Configure repository to return the entity when findOneBy is called
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with(['id' => $entityId])
            ->willReturn($entity);

        // DummyCRUDEntityService doesn't use the logger, so we don't need to check if it's called

        // The entity manager should remove and flush the entity
        $this->entityManagerMock->expects(self::once())
            ->method('remove')
            ->with($entity);
        $this->entityManagerMock->expects(self::once())
            ->method('flush');

        // Act
        $this->service->handleDelete($entityId, $context);
    }
}
