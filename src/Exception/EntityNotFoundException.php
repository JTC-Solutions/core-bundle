<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Exception;

use Doctrine\ORM\EntityNotFoundException as DoctrineEntityNotFoundException;
use JtcSolutions\Core\Entity\IEntity;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Exception thrown when an entity cannot be found in the persistence layer (e.g., database).
 * This is a more specific, translatable version often wrapping Doctrine's own EntityNotFoundException.
 *
 * It differentiates between searches by ID and searches by other parameters.
 * Typically results in an HTTP 422 Unprocessable Entity or 404 Not Found response, depending on context
 * (defaulting here to 422 based on the constructor).
 */
class EntityNotFoundException extends TranslatableException
{
    /**
     * Protected constructor to enforce the use of static factory methods.
     * Initializes the exception with a message, translation details, and HTTP status code.
     *
     * @param string $message The detailed exception message (primarily for logging).
     * @param string $translationCode The translation key used to fetch a user-friendly message.
     * @param int<0, max> $code The HTTP status code associated with this exception. Defaults to 422 (Unprocessable Entity).
     * @param array<string, int|string> $translationParameters Parameters to pass to the translation system.
     * @param Throwable|null $previous The previous throwable (often the underlying Doctrine exception).
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
     * Creates an exception instance for cases where an entity was searched for by its unique ID.
     *
     * @param UuidInterface $id The UUID that was used for the search.
     * @param class-string<IEntity> $entityType The class name of the entity type that was not found.
     * @param DoctrineEntityNotFoundException $doctrineEntityNotFoundException The original Doctrine exception.
     * @return self A new instance of EntityNotFoundException.
     */
    public static function searchedForById(
        UuidInterface $id,
        string $entityType,
        DoctrineEntityNotFoundException $doctrineEntityNotFoundException,
    ): self {
        return new self(
            message: sprintf(
                'Entity %s was not found by id %s',
                $id->toString(),
                $entityType,
            ),
            translationCode: 'entity_not_found',
            previous: $doctrineEntityNotFoundException,
        );
    }

    /**
     * Creates an exception instance for cases where an entity was searched for using a set of parameters (criteria).
     *
     * @param class-string<IEntity> $entityType The class name of the entity type that was not found.
     * @param array<string, int|string|bool|null|UuidInterface> $params The parameters (field => value) used in the search query.
     * @param DoctrineEntityNotFoundException $doctrineEntityNotFoundException The original Doctrine exception.
     * @return self A new instance of EntityNotFoundException.
     */
    public static function searchedForByParams(
        string $entityType,
        array $params,
        DoctrineEntityNotFoundException $doctrineEntityNotFoundException,
    ): self {
        return new self(
            message: sprintf(
                'Entity %s was not found by params %s',
                $entityType,
                json_encode($params),
            ),
            translationCode: 'entity_not_found',
            previous: $doctrineEntityNotFoundException,
        );
    }
}
