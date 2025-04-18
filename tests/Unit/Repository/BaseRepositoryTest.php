<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\Repository;

// Doctrine components to mock
use Doctrine\ORM\AbstractQuery; // Keep this for understanding, but mock Query specifically
use Doctrine\ORM\EntityManagerInterface; // *** Import the specific Query class ***
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\EntityRepository;
// Needed for EntityRepository mock
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;

// Dependencies and helpers
// For TestEntity
use Doctrine\ORM\QueryBuilder;
use JtcSolutions\Core\Tests\Fixtures\Concrete\Entity\TestEntity;
use JtcSolutions\Core\Tests\Fixtures\Concrete\Repository\ConcreteTestRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class BaseRepositoryTest extends TestCase
{
    private MockObject|EntityManagerInterface $entityManagerMock;

    private MockObject|EntityRepository $entityRepositoryMock;

    private MockObject|QueryBuilder $queryBuilderMock;

    private MockObject|AbstractQuery $queryMock; // Use AbstractQuery for broader compatibility

    private string $testEntityClass = TestEntity::class;

    private ConcreteTestRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the core dependencies
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->entityRepositoryMock = $this->createMock(EntityRepository::class);
        $this->queryBuilderMock = $this->createMock(QueryBuilder::class);
        // *** Mock the specific Query class, not AbstractQuery ***
        $this->queryMock = $this->createMock(Query::class);

        // --- Mock Interactions ---
        // Define general mock behavior.

        // 1. EntityManager -> getRepository -> EntityRepository
        $this->entityManagerMock->method('getRepository')
            ->with($this->testEntityClass)
            ->willReturn($this->entityRepositoryMock);

        // 2. EntityRepository -> createQueryBuilder -> QueryBuilder
        $this->entityRepositoryMock->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        // 3. QueryBuilder -> getQuery -> Query (Mocked Query object)
        // This now returns a mock object compatible with the Query return type hint
        $this->queryBuilderMock->method('getQuery')
            ->willReturn($this->queryMock);

        // --- Mock Fluent Interface for QueryBuilder ---
        // Make common QB methods return the mock itself ($this) to allow chaining
        $this->queryBuilderMock->method('select')->willReturnSelf();
        $this->queryBuilderMock->method('andWhere')->willReturnSelf();
        $this->queryBuilderMock->method('setParameter')->willReturnSelf();
        $this->queryBuilderMock->method('setParameters')->willReturnSelf();
        $this->queryBuilderMock->method('setFirstResult')->willReturnSelf();
        $this->queryBuilderMock->method('setMaxResults')->willReturnSelf();
        $this->queryBuilderMock->method('orderBy')->willReturnSelf();
        $this->queryBuilderMock->method('addOrderBy')->willReturnSelf();
        $this->queryBuilderMock->method('innerJoin')->willReturnSelf();
        $this->queryBuilderMock->method('leftJoin')->willReturnSelf();
        $this->queryBuilderMock->method('getAllAliases')->willReturn([]);

        // Instantiate the concrete repository for testing
        $this->repository = new ConcreteTestRepository(
            $this->entityManagerMock,
        );
    }

    public function testGetEntityNameReturnsClassName(): void
    {
        self::assertEquals($this->testEntityClass, $this->repository->getEntityName());
    }

    public function testFindSuccess(): void
    {
        $id = Uuid::uuid4();
        $expectedEntity = new TestEntity($id);

        // Expect specific QB setup for find()
        $this->queryBuilderMock->expects(self::once())
            ->method('andWhere')
            ->with('entity.id = :id');
        $this->queryBuilderMock->expects(self::once())
            ->method('setParameter')
            ->with('id', $id);
        $this->queryBuilderMock->expects(self::once())
            ->method('getQuery') // This will return the mock of Query
            ->willReturn($this->queryMock);

        // Expect query execution on the Query mock
        $this->queryMock->expects(self::once())
            ->method('getSingleResult')
            ->willReturn($expectedEntity);

        $result = $this->repository->find($id);

        self::assertSame($expectedEntity, $result);
    }

    public function testFindNotFoundThrowsException(): void
    {
        $id = Uuid::uuid4();

        // Expect specific QB setup
        $this->queryBuilderMock->expects(self::once())->method('andWhere');
        $this->queryBuilderMock->expects(self::once())->method('setParameter');
        $this->queryBuilderMock->expects(self::once())
            ->method('getQuery') // This will return the mock of Query
            ->willReturn($this->queryMock);

        // Expect query execution on the Query mock to throw NoResultException
        $this->queryMock->expects(self::once())
            ->method('getSingleResult')
            ->willThrowException(new NoResultException());

        // Expect the repository to catch NoResultException and throw its custom one
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage(sprintf('Entity not found in %s by find() with id %s', $this->testEntityClass, $id->toString()));

        $this->repository->find($id);
    }

    public function testFindAll(): void
    {
        $entity1 = new TestEntity(Uuid::uuid4());
        $entity2 = new TestEntity(Uuid::uuid4());
        $expectedEntities = [$entity1, $entity2];

        $this->queryBuilderMock->expects(self::once())
            ->method('getQuery') // This will return the mock of Query
            ->willReturn($this->queryMock);

        // Expect query execution on the Query mock
        $this->queryMock->expects(self::once())
            ->method('getResult')
            ->willReturn($expectedEntities);

        $result = $this->repository->findAll();

        self::assertSame($expectedEntities, $result);
    }
}
