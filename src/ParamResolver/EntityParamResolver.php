<?php declare(strict_types = 1);

namespace JtcSolutions\Core\ParamResolver;

use Doctrine\ORM\EntityNotFoundException as DoctrineEntityNotFoundException;
use Exception;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Repository\IEntityRepository;
use JtcSolutions\Core\Service\RepositoryLocator;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves controller action arguments that are type-hinted with a class implementing IEntity.
 * It retrieves the corresponding entity from the database using a request attribute
 * (e.g., route parameter or query parameter) whose name matches the argument name.
 * The value of this request attribute is expected to be a valid UUID string.
 */
class EntityParamResolver implements ValueResolverInterface
{
    /**
     * @param RepositoryLocator $repositoryLocator Service to find the appropriate repository for a given entity class.
     */
    public function __construct(
        private readonly RepositoryLocator $repositoryLocator,
    ) {
    }

    /**
     * Attempts to resolve a controller argument type-hinted with an IEntity implementation.
     *
     * It looks for a request attribute (route parameter usually) with the same name as the argument.
     * If found, it treats the value as a UUID, finds the corresponding entity using the appropriate repository,
     * and yields the entity.
     *
     * @param Request $request The current request object.
     * @param ArgumentMetadata $argument Metadata about the controller argument being resolved.
     * @return iterable<IEntity> An iterable containing the resolved IEntity instance, or empty if not applicable.
     * @throws BadRequestHttpException If the request parameter value is not a string or not a valid UUID.
     * @throws NotFoundHttpException If the entity cannot be found for the given UUID (converted from Doctrine's exception).
     * @throws Exception If the RepositoryLocator fails to find a repository (should ideally be a more specific exception).
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $argumentType = $argument->getType();

        // Check if the argument type-hint is a class that implements IEntity
        if (
            $argumentType === null
            || ! class_exists($argumentType) // Ensure class exists before is_subclass_of
            || ! is_subclass_of($argumentType, IEntity::class)
        ) {
            // Not an entity type we can resolve, return empty iterable to pass to the next resolver
            return [];
        }

        // Find the repository for the required entity type
        // RepositoryLocator::locate might throw an exception if repository not found, which is okay here.
        /** @var IEntityRepository<IEntity> $repository */
        $repository = $this->repositoryLocator->locate($argumentType);

        $parameterName = $argument->getName();
        $parameterValue = $request->get($parameterName);

        if ($parameterValue === null) {
            throw new BadRequestHttpException(sprintf('Missing required parameter "%s" to resolve entity of type "%s".', $parameterName, $argumentType));
        }

        if (! is_string($parameterValue)) {
            // Parameter value exists but is not a string (e.g., an array in query params)
            throw new BadRequestHttpException(sprintf('Parameter "%s" must be a string UUID, received "%s".', $parameterName, gettype($parameterValue)));
        }

        try {
            $uuid = Uuid::fromString($parameterValue);
        } catch (InvalidUuidStringException $e) {
            // The string value is not a valid UUID format
            throw new BadRequestHttpException(sprintf('Parameter "%s" is not a valid UUID: "%s".', $parameterName, $parameterValue), $e);
        }

        try {
            // The repository's find method should throw its specific EntityNotFoundException on failure.
            $entity = $repository->find($uuid);
            yield $entity;
        } catch (DoctrineEntityNotFoundException $e) {
            // Catch the specific exception from the repository
            // Convert Doctrine's exception to a user-friendly HTTP exception.
            throw new NotFoundHttpException(sprintf('Entity of type "%s" with ID "%s" not found.', $argumentType, $uuid->toString()), $e);
        }
    }
}
