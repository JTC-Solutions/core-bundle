<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Functional\Controller;

use JtcSolutions\Core\Tests\Fixtures\Dummy\Controller\DummyController;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Dto\DummyCreateRequest;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Entity\DummyEntity;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Service\DummyCRUDEntityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DummyControllerTest extends TestCase
{
    private const string UUID = 'abeac5d3-d316-44ae-a584-87f74c4cce0e';

    private MockObject|ValidatorInterface $validatorMock;

    private MockObject|DummyCRUDEntityService $serviceMock;

    private MockObject|LoggerInterface $loggerMock;

    private DummyController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validatorMock = $this->createMock(ValidatorInterface::class);
        $this->serviceMock = $this->createMock(DummyCRUDEntityService::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $container = new Container();


        $this->controller = new DummyController(
            $this->serviceMock,
        );
        $this->controller->setLogger($this->loggerMock);
        $this->controller->setValidator($this->validatorMock);
        $this->controller->setContainer($container);
    }

    public function testHandleCreateSuccess(): void
    {
        $dummyEntity = new DummyEntity(
            id: Uuid::fromString(self::UUID),
            string: 'string',
            integer: 1,
            float: 1.1,
            contextString: 'string',
        );

        $requestBody = new DummyCreateRequest(
            string: 'string',
            integer: 1,
            float: 1.1,
        );

        $this->validatorMock->expects(self::once())
            ->method('validate')
            ->with($requestBody)
            ->willReturn(new ConstraintViolationList());

        $this->serviceMock->expects(self::once())
            ->method('handleCreate')
            ->with($requestBody)
            ->willReturn($dummyEntity);

        $response = $this->controller->create($requestBody);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);

        self::assertIsArray($responseData);
        self::assertArrayHasKey('id', $responseData);
        self::assertEquals($dummyEntity->id->toString(), $responseData['id']);
        self::assertEquals($dummyEntity->string, $responseData['string']);
        self::assertEquals($dummyEntity->float, $responseData['float']);
        self::assertEquals($dummyEntity->integer, $responseData['integer']);
    }

    public function testHandleCreateValidationError(): void
    {
        $requestBody = new DummyCreateRequest(
            string: '',
            integer: -1,
            float: 1.1,
        );

        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'String cannot be empty.', // Message
                '', // Message template
                [], // Parameters
                $requestBody, // Root
                'string', // Property path
                '', // Invalid value
            ),
            new ConstraintViolation(
                'Integer must be positive.', // Message
                '', // Message template
                [], // Parameters
                $requestBody, // Root
                'integer', // Property path
                -5, // Invalid value
            ),
        ]);

        $this->validatorMock->expects(self::once())
            ->method('validate')
            ->with($requestBody)
            ->willReturn($violations);

        // Ensure the service is never called if validation fails
        $this->serviceMock->expects(self::never())
            ->method('handleCreate');

        // Expect logger to be called for errors
        $this->loggerMock->expects(self::once())
            ->method('error')
            ->with('Validation errors', ['errors' => $violations]);

        $response = $this->controller->create($requestBody);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);

        // Assert the structure of the validation error response
        // BaseEntityCRUDController returns ['message' => (string)$errors]
        // You might want to adjust BaseEntityCRUDController for a better error format.
        self::assertIsArray($responseData);
        self::assertArrayHasKey('message', $responseData);
        self::assertStringContainsString('String cannot be empty.', $responseData['message']);
        self::assertStringContainsString('Integer must be positive.', $responseData['message']);
    }

    public function testHandleCreateLogsDebugOnValidationSuccess(): void
    {
        $requestBody = new DummyCreateRequest(
            string: 'string',
            integer: 1,
            float: 1.1,
        );

        $dummyEntity = new DummyEntity(
            id: Uuid::fromString(self::UUID),
            string: 'test string',
            integer: 123,
            float: 4.56,
            contextString: 'test context',
        );

        $this->validatorMock->expects(self::once())
            ->method('validate')
            ->with($requestBody)
            ->willReturn(new ConstraintViolationList()); // No violations

        $this->serviceMock->expects(self::once())
            ->method('handleCreate')
            ->willReturn($dummyEntity);

        // Expect debug log after validation passes
        $this->loggerMock->expects(self::once())
            ->method('debug')
            ->with('Validation passed');
        // Ensure error log is not called
        $this->loggerMock->expects(self::never())
            ->method('error');

        $this->controller->create($requestBody);

        // Assertions on response are in testHandleCreateSuccess,
        // this test focuses solely on the logging interaction.
    }
}
