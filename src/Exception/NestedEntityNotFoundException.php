<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Exception;

use Doctrine\ORM\EntityNotFoundException;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class NestedEntityNotFoundException extends TranslatableException
{
    protected function __construct(
        string $message,
        string $translationCode,
        int $code = Response::HTTP_UNPROCESSABLE_ENTITY,
        array $translationParameters = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $translationCode, $translationParameters, $previous);
    }

    public static function create(
        UuidInterface $searchedEntity,
        string $searchedEntityType,
        string $parentEntityType,
        EntityNotFoundException $doctrineNotFoundException,
    ): self {
        return new self(
            message: sprintf(
                'Nested entity %s with id %s was not found for parent entity %s.',
                $searchedEntityType,
                $searchedEntity->toString(),
                $parentEntityType,
            ),
            translationCode: 'nested_entity_not_found',
            previous: $doctrineNotFoundException,
        );
    }
}
