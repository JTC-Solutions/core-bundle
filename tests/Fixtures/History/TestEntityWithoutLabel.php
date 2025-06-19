<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use JtcSolutions\Core\Entity\IEntity;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class TestEntityWithoutLabel implements IEntity
{
    private UuidInterface $id;

    /**
     * @var non-empty-string
     */
    private string $value;

    /**
     * @param non-empty-string $value
     */
    public function __construct(string $value = 'No Label Entity')
    {
        $this->id = Uuid::uuid4();
        $this->value = $value;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @param non-empty-string $value
     */
    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }
}
