<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Exception;

use Doctrine\ORM\EntityNotFoundException;
use JtcSolutions\Core\Entity\IEntity;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Exception thrown when an operation (like creating or updating a 'parent' entity)
 * fails because a related ('nested') entity, referenced by its ID in the request data,
 * could not be found in the database.
 *
 * This typically indicates invalid input data provided by the client, hence the default
 * HTTP 422 Unprocessable Entity status code.
 */
class NestedEntityNotFoundException extends TranslatableException
{
    /**
     * Protected constructor to enforce the use of the static factory method `create`.
     * Initializes the exception with a message, translation details, and HTTP status code.
     *
     * @param string $message The detailed exception message (primarily for logging).
     * @param string $translationCode The translation key used to fetch a user-friendly message.
     * @param int<0, max> $code The HTTP status code associated with this exception. Defaults to 422 (Unprocessable Entity).
     * @param array<string, int|string> $translationParameters Parameters to pass to the translation system.
     * @param Throwable|null $previous The previous throwable (often the underlying Doctrine EntityNotFoundException).
     */
    protected function __construct(
        string $message,
        string $translationCode,
        int $code = Response::HTTP_UNPROCESSABLE_ENTITY,
        array $translationParameters = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $translationCode, $translationParameters, $previous);
    }

    /**
     * Creates a new instance of the exception indicating a related entity was not found.
     *
     * @param UuidInterface $searchedEntityId The UUID of the nested entity that was searched for but not found.
     * @param class-string<IEntity> $searchedEntityType The class name of the nested entity type that was not found.
     * @param class-string<IEntity> $parentEntityType The class name of the main ('parent') entity being processed when the error occurred.
     * @param EntityNotFoundException $doctrineNotFoundException The original Doctrine exception that triggered this error.
     * @return self A new instance of NestedEntityNotFoundException.
     */
    public static function create(
        UuidInterface $searchedEntityId,
        string $searchedEntityType,
        string $parentEntityType,
        EntityNotFoundException $doctrineNotFoundException,
    ): self {
        return new self(
            message: sprintf(
                'Nested entity %s with id %s was not found for parent entity %s.',
                $searchedEntityType,
                $searchedEntityId->toString(),
                $parentEntityType,
            ),
            translationCode: 'nested_entity_not_found',
            previous: $doctrineNotFoundException,
        );
    }
}
