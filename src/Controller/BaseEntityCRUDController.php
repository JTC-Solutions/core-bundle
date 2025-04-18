<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Controller;

use Exception;
use JtcSolutions\Core\Dto\IEntityRequestBody;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Exception\EntityAlreadyExistsException;
use JtcSolutions\Core\Exception\EntityNotFoundException;
use JtcSolutions\Core\Service\IEntityService;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Provides a base implementation for controllers performing CRUD operations on entities.
 * Designed to work with a corresponding IEntityService implementation.
 * Handles request validation, service calls, and JSON response generation.
 *
 * @template TEntity of IEntity
 * @template TRequestBody of IEntityRequestBody
 * @template TEntityService of IEntityService<TEntity, TRequestBody>
 */
abstract class BaseEntityCRUDController extends BaseController
{
    protected ValidatorInterface $validator;

    protected LoggerInterface $logger;

    /**
     * @param TEntityService $service The specific entity service handling business logic.
     */
    public function __construct(
        protected readonly IEntityService $service,
    ) {
    }

    /** @internal */
    #[Required]
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    /** @internal */
    #[Required]
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Validates the incoming request body DTO using the Symfony Validator.
     * Logs validation errors if any occur.
     *
     * @param TRequestBody $requestBody The DTO to validate.
     * @return JsonResponse|null A JsonResponse containing validation errors (HTTP 400) if validation fails,
     *                           otherwise null if validation passes.
     */
    protected function validate(IEntityRequestBody $requestBody): JsonResponse|null
    {
        $errors = $this->validator->validate($requestBody);
        if (count($errors) > 0) {
            $this->logger->error('Validation errors', ['errors' => $errors]);
            return $this->json(['message' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }
        $this->logger->debug('Validation passed');

        return null;
    }

    /**
     * Handles the update request for an existing entity.
     * Validates the request body and delegates the update operation to the service.
     *
     * @param UuidInterface $id The UUID of the entity to update.
     * @param TRequestBody $requestBody The DTO containing updated entity data.
     * @param string[] $serializerGroups Serialization groups to use for the response.
     * @return JsonResponse A JSON response containing the updated entity on success (HTTP 200)
     *                     or validation errors (HTTP 400).
     * @throws EntityNotFoundException If the entity with the given ID is not found.
     * @throws EntityAlreadyExistsException If the update causes a unique constraint violation.
     * @throws Exception For other potential service layer exceptions.
     */
    protected function handleUpdate(
        UuidInterface $id,
        IEntityRequestBody $requestBody,
        array $serializerGroups = [],
    ): JsonResponse {
        $result = $this->validate($requestBody);
        if ($result !== null) {
            return $result;
        }

        $entity = $this->service->handleUpdate($id, $requestBody);

        return $this->json($entity, Response::HTTP_OK, [], ['groups' => $serializerGroups]);
    }

    /**
     * Handles the deletion request for an entity.
     * Delegates the deletion operation to the service.
     *
     * @param UuidInterface|TEntity $id The UUID or the Entity instance to delete.
     *        Using the Entity instance might be relevant if it was already fetched (e.g., by EntityParamResolver).
     * @return JsonResponse An empty JSON response with HTTP 204 (No Content) on success.
     * @throws EntityNotFoundException If the entity with the given ID is not found.
     * @throws Exception For other potential service layer exceptions during deletion.
     */
    protected function handleDelete(UuidInterface|IEntity $id): JsonResponse
    {
        $this->service->handleDelete($id);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Handles the creation request for a new entity.
     * Validates the request body and delegates the creation operation to the service.
     * Intended to be called from implementing controller's specific create action.
     *
     * @param TRequestBody $requestBody The DTO containing the new entity data.
     * @param string[] $serializerGroups Serialization groups to use for the response.
     * @return JsonResponse A JSON response containing the newly created entity (HTTP 201)
     *                     or validation errors (HTTP 400).
     * @throws EntityAlreadyExistsException If the new entity violates a unique constraint.
     * @throws Exception For other potential service layer exceptions.
     */
    protected function handleCreate(
        IEntityRequestBody $requestBody,
        array $serializerGroups = [],
    ): JsonResponse {
        $result = $this->validate($requestBody);
        if ($result !== null) {
            return $result;
        }

        /** @var TEntity $entity */
        $entity = $this->service->handleCreate($requestBody);

        return $this->json($entity, Response::HTTP_CREATED, [], ['groups' => $serializerGroups]);
    }
}
