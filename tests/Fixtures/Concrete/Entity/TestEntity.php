<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\Concrete\Entity;

use JtcSolutions\Core\Entity\IEntity;
use Ramsey\Uuid\UuidInterface;

class TestEntity implements IEntity
{
    public function __construct(
        private UuidInterface $id,
    ) {
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }
}
