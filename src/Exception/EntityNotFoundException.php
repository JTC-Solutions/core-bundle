<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Exception;

use Doctrine\ORM\EntityNotFoundException as DoctrineEntityNotFoundException;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EntityNotFoundException extends TranslatableException
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
     * @param array<string, mixed> $params
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
