<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Unit\ParamResolver; // Adjust namespace if needed

use JtcSolutions\Core\ParamResolver\UuidQueryParamResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException; // Assuming this is used for errors

class UuidQueryParamResolverTest extends TestCase
{
    // --- Test Constants ---
    private const string TEST_PARAM_NAME = 'identifier';

    private const string VALID_UUID_STRING = 'a1b2c3d4-e5f6-7890-1234-567890abcdef';

    private const string INVALID_UUID_STRING = 'not-a-valid-uuid';

    private UuidQueryParamResolver $resolver;

    private MockObject|Request $mockRequest;

    private MockObject|ArgumentMetadata $mockArgument;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRequest = $this->createMock(Request::class);
        $this->mockArgument = $this->createMock(ArgumentMetadata::class);

        $this->resolver = new UuidQueryParamResolver(); // Assuming no constructor dependencies
    }

    // --- Test Cases ---

    public function testResolveSuccessReturnsUuid(): void
    {
        // Arrange
        $this->configureMocks(UuidInterface::class);
        $expectedUuid = Uuid::fromString(self::VALID_UUID_STRING);

        // Act
        $result = $this->resolver->resolve($this->mockRequest, $this->mockArgument);
        $resolvedValue = iterator_to_array($result); // Convert generator to array

        // Assert
        self::assertCount(1, $resolvedValue, 'Should yield exactly one value.');
        self::assertInstanceOf(UuidInterface::class, $resolvedValue[0]);
        self::assertEquals($expectedUuid, $resolvedValue[0], 'Yielded value should be the correct UUID object.');
    }

    public function testResolveArgumentTypeNotUuidInterfaceReturnsEmptyArray(): void
    {
        // Arrange
        $this->configureMocks(stdClass::class); // Use a different class type

        // Act
        $result = $this->resolver->resolve($this->mockRequest, $this->mockArgument);

        // Assert
        self::assertSame([], iterator_to_array($result), 'Should return empty iterable if argument type is not UuidInterface.');
    }

    public function testResolveArgumentTypeIsNullReturnsEmptyArray(): void
    {
        // Arrange
        $this->configureMocks(null); // Type is null

        // Act
        $result = $this->resolver->resolve($this->mockRequest, $this->mockArgument);

        // Assert
        self::assertSame([], iterator_to_array($result), 'Should return empty iterable if argument type is null.');
    }

    public function testResolveInvalidUuidStringThrowsException(): void
    {
        $this->configureMocks(UuidInterface::class, paramValue: self::INVALID_UUID_STRING);
        $this->expectException(BadRequestHttpException::class);
        iterator_to_array($this->resolver->resolve($this->mockRequest, $this->mockArgument));
    }

    public function testResolveParameterValueNotStringThrowsException(): void
    {
        // Arrange
        $this->configureMocks(UuidInterface::class, paramValue: 12_345); // Pass an integer

        // Assert: Expecting an exception indicating wrong data type
        $this->expectException(BadRequestHttpException::class);
        // Optionally, check the message
        // $this->expectExceptionMessage(sprintf('Parameter "%s" must be a string UUID.', self::TEST_PARAM_NAME));

        // Act: Must trigger iteration
        iterator_to_array($this->resolver->resolve($this->mockRequest, $this->mockArgument));
    }

    // --- Helper to configure mocks ---
    private function configureMocks(
        ?string $argumentType,
        string $paramName = self::TEST_PARAM_NAME,
        mixed $paramValue = self::VALID_UUID_STRING,
        bool $paramExists = true,
    ): void {
        $this->mockArgument->method('getType')->willReturn($argumentType);
        $this->mockArgument->method('getName')->willReturn($paramName);

        // Use request attributes or query based on common usage
        // Let's assume query parameters 'get' for this example
        if ($paramExists) {
            $this->mockRequest->method('get')->with($paramName)->willReturn($paramValue);
        } else {
            $this->mockRequest->method('get')->with($paramName)->willReturn(null); // Simulate missing param
        }
    }
}
