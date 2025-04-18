<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Exception;

use JtcSolutions\Core\Entity\IEntity;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when an attempt is made to create or update an entity
 * that would violate a uniqueness constraint (e.g., creating an entity
 * with a property value that must be unique but already exists in another entity).
 * Typically results in an HTTP 409 Conflict response.
 */
class EntityAlreadyExistsException extends TranslatableException
{
    /**
     * Protected constructor to enforce the use of the static factory method `create`.
     * Initializes the exception with a message, translation details, and HTTP status code.
     *
     * @param string $message The detailed exception message (primarily for logging).
     * @param string $translationCode The translation key used to fetch a user-friendly message.
     * @param int<0, max> $code The HTTP status code associated with this exception. Defaults to 409 (Conflict).
     * @param array<string, int|string> $translationParameters Parameters to pass to the translation system.
     */
    protected function __construct(
        string $message,
        string $translationCode,
        int $code = Response::HTTP_CONFLICT,
        array $translationParameters = [],
    ) {
        parent::__construct($message, $code, $translationCode, $translationParameters);
    }

    /**
     * Creates a new instance of the exception with a standardized message.
     *
     * @param class-string<IEntity> $entityClass The fully qualified class name of the entity type that already exists.
     * @param array<string, int|string|bool|null> $params The parameters (field => value) used in the query that found the existing entity.
     * @param UuidInterface|null $ignoredDuplicity Optional. If provided, specifies the UUID of an entity that should be ignored
     *                                            during the duplicity check (useful during update operations where the entity
     *                                            being updated might match the criteria).
     * @return self A new instance of EntityAlreadyExistsException.
     */
    public static function create(
        string $entityClass,
        array $params,
        ?UuidInterface $ignoredDuplicity = null,
    ): self {
        $paramsJson = json_encode($params) ?: 'invalid json';

        if ($ignoredDuplicity !== null) {
            $message = sprintf(
                'Entity %s already exists, you can not create duplicate. It was looked for by params: %s, with duplicity ignoring entity %s',
                $entityClass,
                $paramsJson,
                $ignoredDuplicity->toString(),
            );
        } else {
            $message = sprintf(
                'Entity %s already exists, you can not create duplicate. It was looked for by params: %s',
                $entityClass,
                $paramsJson,
            );
        }

        return new self(
            message: $message,
            translationCode: 'entity_already_exists',
        );
    }
}
