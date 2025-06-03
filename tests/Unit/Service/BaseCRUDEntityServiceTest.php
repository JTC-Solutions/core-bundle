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
            $this->loggerMock,
            $this->entityManagerMock,
        );

        // Explicitly set the logger since it's not set in the constructor but via #[Required] attribute
        $this->service->setLogger($this->loggerMock);
    }

    public function testHandleCreatePassesContextToMapDataAndCallCreate(): void
    {
        // Arrange
        $requestBody = new DummyCreateRequest('test string', 123, 45.67);
        $context = ['log_message' => 'Creating entity with context'];
        $expectedEntity = new DummyEntity(Uuid::uuid4(), 'test string', 123, 45.67);

        // The logger should be called with the context message
        $this->loggerMock->expects(self::once())
            ->method('info')
            ->with(
                $context['log_message'],
                self::callback(function (array $logContext) {
                    return $logContext['operation'] === 'create'
                        && isset($logContext['data'])
                        && $logContext['data']['string'] === 'test string'
                        && $logContext['data']['integer'] === 123
                        && $logContext['data']['float'] === 45.67;
                })
            );

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
    }

    public function testHandleUpdatePassesContextToMapDataAndCallUpdate(): void
    {
        // Arrange
        $entityId = Uuid::uuid4();
        $entity = new DummyEntity($entityId, 'old string', 456, 78.9);
        $requestBody = new DummyCreateRequest('updated string', 789, 12.34);
        $context = ['log_message' => 'Updating entity with context'];

        // Configure repository to return the entity when findOneBy is called
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with(['id' => $entityId])
            ->willReturn($entity);

        // The logger should be called with the context message
        $this->loggerMock->expects(self::once())
            ->method('info')
            ->with(
                $context['log_message'],
                self::callback(function (array $logContext) use ($entityId) {
                    return $logContext['operation'] === 'update'
                        && $logContext['entity_id'] === $entityId->toString();
                })
            );

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
    }

    public function testHandleDeletePassesContextToDelete(): void
    {
        // Arrange
        $entityId = Uuid::uuid4();
        $entity = new DummyEntity($entityId, 'test string', 123, 45.67);
        $context = ['log_message' => 'Deleting entity with context'];

        // Configure repository to return the entity when findOneBy is called
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with(['id' => $entityId])
            ->willReturn($entity);

        // The logger should be called with the context message
        $this->loggerMock->expects(self::once())
            ->method('info')
            ->with(
                $context['log_message'],
                ['entity_id' => $entityId->toString()]
            );

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

        // The logger should not be called
        $this->loggerMock->expects(self::never())
            ->method('info');

        // The entity manager should persist and flush the entity
        $this->entityManagerMock->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(DummyEntity::class));
        $this->entityManagerMock->expects(self::once())
            ->method('flush');

        // Act
        $result = $this->service->handleCreate($requestBody);

        // Assert
        self::assertInstanceOf(DummyEntity::class, $result);
        self::assertEquals('test string', $result->getString());
        self::assertEquals(123, $result->getInteger());
        self::assertEquals(45.67, $result->getFloat());
    }

    public function testHandleUpdateWithoutContext(): void
    {
        // Arrange
        $entityId = Uuid::uuid4();
        $entity = new DummyEntity($entityId, 'old string', 456, 78.9);
        $requestBody = new DummyCreateRequest('updated string', 789, 12.34);

        // Configure repository to return the entity when findOneBy is called
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with(['id' => $entityId])
            ->willReturn($entity);

        // The logger should not be called
        $this->loggerMock->expects(self::never())
            ->method('info');

        // The entity manager should flush the changes
        $this->entityManagerMock->expects(self::once())
            ->method('flush');

        // Act
        $result = $this->service->handleUpdate($entityId, $requestBody);

        // Assert
        self::assertInstanceOf(DummyEntity::class, $result);
        self::assertEquals('updated string', $result->getString());
        self::assertEquals(789, $result->getInteger());
        self::assertEquals(12.34, $result->getFloat());
    }

    public function testHandleDeleteWithoutContext(): void
    {
        // Arrange
        $entityId = Uuid::uuid4();
        $entity = new DummyEntity($entityId, 'test string', 123, 45.67);

        // Configure repository to return the entity when findOneBy is called
        $this->repositoryMock->expects(self::once())
            ->method('findOneBy')
            ->with(['id' => $entityId])
            ->willReturn($entity);

        // The logger should not be called
        $this->loggerMock->expects(self::never())
            ->method('info');

        // The entity manager should remove and flush the entity
        $this->entityManagerMock->expects(self::once())
            ->method('remove')
            ->with($entity);
        $this->entityManagerMock->expects(self::once())
            ->method('flush');

        // Act
        $this->service->handleDelete($entityId);
    }
}
