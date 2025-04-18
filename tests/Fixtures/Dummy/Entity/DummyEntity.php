<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\Dummy\Entity;

use JtcSolutions\Core\Entity\IEntity;
use Ramsey\Uuid\UuidInterface;

class DummyEntity implements IEntity
{
    public UuidInterface $id;

    public string $string;

    public int $integer;

    public float $float;

    public function __construct(
        UuidInterface $id,
        string $string,
        int $integer,
        float $float,
    ) {
        $this->id = $id;
        $this->string = $string;
        $this->integer = $integer;
        $this->float = $float;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getString(): string
    {
        return $this->string;
    }

    public function setString(string $string): void
    {
        $this->string = $string;
    }

    public function getInteger(): int
    {
        return $this->integer;
    }

    public function setInteger(int $integer): void
    {
        $this->integer = $integer;
    }

    public function getFloat(): float
    {
        return $this->float;
    }

    public function setFloat(float $float): void
    {
        $this->float = $float;
    }
}
