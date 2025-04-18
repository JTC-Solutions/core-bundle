<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Controller;

use JtcSolutions\Core\Dto\IEntityRequestBody;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Service\IEntityService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @template TEntity of IEntity
 * @template TRequestBody of IEntityRequestBody
 * @template TEntityService of IEntityService<TEntity, TRequestBody>
 */
abstract class BaseEntityCRUDController extends BaseController
{
    /**
     * @param TEntityService $service
     */
    public function __construct(
        protected readonly ValidatorInterface $validator,
        protected readonly IEntityService $service,
        protected readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param TRequestBody $requestBody
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
     * @param TRequestBody $requestBody
     * @param string[] $serializerGroups
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
