<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\ParamResolver;

use Doctrine\ORM\NoResultException;
use Exception;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\ParamResolver\EntityParamResolver;
use JtcSolutions\Core\Repository\IEntityRepository;
use JtcSolutions\Core\Service\RepositoryLocator;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Entity\DummyEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface; // For RepositoryLocator exception
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable; // Import the expected exception

class EntityParamResolverTest extends TestCase
{
    private const string TEST_ENTITY_CLASS = DummyEntity::class;

    private const string TEST_PARAM_NAME = 'dummyId';

    private const string VALID_UUID_STRING = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    private MockObject|RepositoryLocator $mockRepositoryLocator;

    private EntityParamResolver $resolver;

    private MockObject|Request $mockRequest;

    private MockObject|ArgumentMetadata $mockArgument;

    private ?UuidInterface $parsedUuid; // Store parsed UUID for convenience

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRepositoryLocator = $this->createMock(RepositoryLocator::class);
        $this->mockRequest = $this->createMock(Request::class);
        $this->mockArgument = $this->createMock(ArgumentMetadata::class);
        $this->parsedUuid = Uuid::fromString(self::VALID_UUID_STRING); // Parse once

        $this->resolver = new EntityParamResolver($this->mockRepositoryLocator);
    }

    // --- Test Cases ---

    public function testResolveSuccessReturnsEntity(): void
    {
        // Arrange
        $expectedEntity = $this->createMock(DummyEntity::class);
        $mockRepository = $this->configureMocksForAttempt();
        // Mock the find method for success
        $mockRepository->method('find')->with($this->parsedUuid)->willReturn($expectedEntity);

        // Act
        $result = $this->resolver->resolve($this->mockRequest, $this->mockArgument);
        $resolvedValue = iterator_to_array($result);

        // Assert
        self::assertCount(1, $resolvedValue);
        self::assertSame($expectedEntity, $resolvedValue[0]);
    }

    public function testResolveArgumentTypeNotEntityReturnsEmptyArray(): void
    {
        // Arrange
        $this->mockArgument->method('getType')->willReturn(stdClass::class); // Not an IEntity subclass

        // Act
        $result = $this->resolver->resolve($this->mockRequest, $this->mockArgument);

        // Assert: Expect an empty array (converted from iterable)
        self::assertSame([], iterator_to_array($result), 'Should return empty iterable if argument type is not an IEntity subclass.');
        $this->mockRepositoryLocator->expects(self::never())->method('locate');
    }

    public function testResolveArgumentTypeIsNullReturnsEmptyArray(): void
    {
        // Arrange
        $this->mockArgument->method('getType')->willReturn(null);

        // Act
        $result = $this->resolver->resolve($this->mockRequest, $this->mockArgument);

        // Assert: Expect an empty array
        self::assertSame([], iterator_to_array($result), 'Should return empty iterable if argument type is null.');
        $this->mockRepositoryLocator->expects(self::never())->method('locate');
    }

    public function testResolveParameterValueNotStringThrowsException(): void
    {
        // Arrange
        $this->configureMocksForAttempt(paramValue: 123); // Pass integer value

        // Assert
        $this->expectException(Throwable::class);

        // Act: Must trigger iteration to hit the exception point
        iterator_to_array($this->resolver->resolve($this->mockRequest, $this->mockArgument));
    }

    public function testResolveParameterValueNullThrowsException(): void
    {
        // Arrange
        $this->configureMocksForAttempt(paramValue: null); // Pass null value

        // Assert
        $this->expectException(Throwable::class);

        // Act: Must trigger iteration
        iterator_to_array($this->resolver->resolve($this->mockRequest, $this->mockArgument));
    }

    public function testResolveInvalidUuidStringThrowsUuidException(): void
    {
        // Arrange
        $invalidUuidString = 'not-a-uuid';
        $this->configureMocksForAttempt(paramValue: $invalidUuidString);

        // Assert
        $this->expectException(BadRequestHttpException::class);

        // Act: Must trigger iteration
        iterator_to_array($this->resolver->resolve($this->mockRequest, $this->mockArgument));
    }

    public function testResolveRepositoryLocatorThrowsException(): void
    {
        // Arrange
        // Only mock ArgumentMetadata, Request 'get' won't be called if locate fails
        $this->mockArgument->method('getType')->willReturn(self::TEST_ENTITY_CLASS);
        $this->mockArgument->method('getName')->willReturn(self::TEST_PARAM_NAME);
        // Don't need to mock request->get() here

        $expectedException = new RuntimeException('Repository not found simulation');
        $this->mockRepositoryLocator->method('locate')
            ->with(self::TEST_ENTITY_CLASS)
            ->willThrowException($expectedException);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Repository not found simulation');

        // Act: Must trigger iteration (although exception happens before yield)
        iterator_to_array($this->resolver->resolve($this->mockRequest, $this->mockArgument));
    }

    // Renamed and updated test for entity not found scenario
    public function testResolveRepositoryFindThrowsNoResultException(): void
    {
        // Arrange
        $mockRepository = $this->configureMocksForAttempt();
        // Mock find to throw the exception expected when entity not found
        $mockRepository->method('find')
            ->with($this->parsedUuid)
            ->willThrowException(new NoResultException());

        // Assert
        $this->expectException(NoResultException::class);

        // Act: Must trigger iteration to execute the 'find' call
        iterator_to_array($this->resolver->resolve($this->mockRequest, $this->mockArgument));
    }

    // Updated helper: configures mocks for a path where resolution is attempted
    // It no longer assumes success, just that the initial checks pass.
    private function configureMocksForAttempt(
        string $entityClass = self::TEST_ENTITY_CLASS,
        string $paramName = self::TEST_PARAM_NAME,
        string|int|null $paramValue = self::VALID_UUID_STRING, // Allow testing different values
    ): MockObject|IEntityRepository {
        $mockRepository = $this->createMock(IEntityRepository::class);

        $this->mockArgument->method('getType')->willReturn($entityClass);
        $this->mockArgument->method('getName')->willReturn($paramName);
        $this->mockRequest->method('get')->with($paramName)->willReturn($paramValue); // Use provided value
        // Only mock locate if we expect it to be called
        if (is_subclass_of($entityClass, IEntity::class)) {
            $this->mockRepositoryLocator->method('locate')->with($entityClass)->willReturn($mockRepository);
        }

        return $mockRepository;
    }
}
