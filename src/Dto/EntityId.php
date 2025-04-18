<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto;

use Ramsey\Uuid\UuidInterface;

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
