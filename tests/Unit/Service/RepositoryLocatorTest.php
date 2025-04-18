<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\Service; // Adjust namespace if needed

use JtcSolutions\Core\Entity\IEntity; // Assuming this base interface exists
use JtcSolutions\Core\Repository\IEntityRepository;
use JtcSolutions\Core\Service\RepositoryLocator;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Entity\AnotherDummyEntity; // Example Entity 1
use JtcSolutions\Core\Tests\Fixtures\Dummy\Entity\DummyEntity; // Example Entity 2
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RepositoryLocatorTest extends TestCase
{
    /**
     * Test that the locator finds and returns the correct repository
     * when it exists in the provided iterable.
     */
    public function testLocateFindsCorrectRepository(): void
    {
        // Arrange
        $dummyEntityFqcn = DummyEntity::class;
        $anotherEntityFqcn = AnotherDummyEntity::class; // Assume this class exists for testing

        // Mock Repository 1 (Incorrect for this test's target)
        $mockRepo1 = $this->createMock(IEntityRepository::class);
        $mockRepo1->method('getEntityName')->willReturn($anotherEntityFqcn);

        // Mock Repository 2 (Correct for this test's target)
        $mockRepo2 = $this->createMock(IEntityRepository::class);
        $mockRepo2->method('getEntityName')->willReturn($dummyEntityFqcn);

        // The iterable simulates the collection injected by Symfony's AutowireIterator
        $repositoriesIterable = [$mockRepo1, $mockRepo2];

        $locator = new RepositoryLocator($repositoriesIterable);

        // Act
        /** @var IEntityRepository<DummyEntity> $foundRepository */
        $foundRepository = $locator->locate($dummyEntityFqcn);

        // Assert
        self::assertSame($mockRepo2, $foundRepository, 'Should have returned the second mock repository.');
        self::assertEquals($dummyEntityFqcn, $foundRepository->getEntityName(), 'The found repository should handle the requested entity.');
    }

    /**
     * Test that the locator throws a RuntimeException when no repository
     * matches the requested entity FQCN.
     */
    public function testLocateThrowsExceptionWhenNotFound(): void
    {
        // Arrange
        $requestedEntityFqcn = DummyEntity::class;
        $otherEntityFqcn = AnotherDummyEntity::class;

        // Mock repositories that DON'T handle the requested entity
        $mockRepo1 = $this->createMock(IEntityRepository::class);
        $mockRepo1->method('getEntityName')->willReturn($otherEntityFqcn);

        $mockRepo2 = $this->createMock(IEntityRepository::class);
        $mockRepo2->method('getEntityName')->willReturn('App\YetAnotherEntity');

        $repositoriesIterable = [$mockRepo1, $mockRepo2];
        $locator = new RepositoryLocator($repositoriesIterable);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Repository not found for entity: ' . $requestedEntityFqcn);

        // Act
        $locator->locate($requestedEntityFqcn);
    }

    /**
     * Test that the locator throws a RuntimeException when the
     * iterable provided during construction is empty.
     */
    public function testLocateThrowsExceptionWhenIterableIsEmpty(): void
    {
        // Arrange
        $requestedEntityFqcn = DummyEntity::class;
        $repositoriesIterable = []; // Empty iterable
        $locator = new RepositoryLocator($repositoriesIterable);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Repository not found for entity: ' . $requestedEntityFqcn);

        // Act
        $locator->locate($requestedEntityFqcn);
    }

    /**
     * Helper method to create mock IEntityRepository if needed elsewhere,
     * although direct creation in tests is often clearer.
     *
     * @param class-string $entityFqcn The entity class this mock should report handling
     * @return MockObject|IEntityRepository<IEntity>
     */
    private function createMockRepositoryHandling(string $entityFqcn): MockObject|IEntityRepository
    {
        $mockRepo = $this->createMock(IEntityRepository::class);
        $mockRepo->method('getEntityName')->willReturn($entityFqcn);

        /** @var MockObject|IEntityRepository<IEntity> $mockRepo */
        return $mockRepo;
    }
}
