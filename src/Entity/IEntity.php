<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Entity;

use Ramsey\Uuid\UuidInterface;

interface IEntity
{
    public function getId(): UuidInterface;
}
