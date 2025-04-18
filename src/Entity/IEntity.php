<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Entity;

use Ramsey\Uuid\UuidInterface;

/**
 * Defines the basic contract for all entities within the application.
 * Ensures that every entity can provide its unique identifier as a UUID.
 */
interface IEntity
{
    /**
     * Retrieves the unique identifier (UUID) of the entity.
     *
     * @return UuidInterface The UUID representing the entity's primary key.
     */
    public function getId(): UuidInterface;
}
