<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Exception;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Response;

class EntityAlreadyExistsException extends TranslatableException
{
    protected function __construct(
        string $message,
        string $translationCode,
        int $code = Response::HTTP_CONFLICT,
        array $translationParameters = [],
    ) {
        parent::__construct($message, $code, $translationCode, $translationParameters);
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function create(
        string $entityClass,
        array $params,
        ?UuidInterface $ignoredDuplicity = null,
    ): self {
        if ($ignoredDuplicity !== null) {
            $message = sprintf(
                'Entity %s already exists, you can not create duplicate. It was looked for by params: %s, with duplicity ignoring entity %s',
                $entityClass,
                json_encode($params),
                $ignoredDuplicity->toString(),
            );
        } else {
            $message = sprintf(
                'Entity %s already exists, you can not create duplicate. It was looked for by params: %s',
                $entityClass,
                json_encode($params),
            );
        }

        return new self(
            message: $message,
            translationCode: 'entity_already_exists',
        );
    }
}
