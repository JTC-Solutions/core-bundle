<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto;

use Ramsey\Uuid\UuidInterface;

/**
 * Represents a simple Data Transfer Object containing only an entity's UUID identifier.
 * Useful for responses or request parts where only the ID is required.
 * This DTO should be used when an entity has relation to another entity, and REST requires only ID from the client.
 */
readonly class EntityId
{
    public function __construct(
        public UuidInterface $id,
    ) {
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }
}
