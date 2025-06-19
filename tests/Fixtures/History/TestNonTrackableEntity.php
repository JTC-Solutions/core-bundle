<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use JtcSolutions\Core\Entity\IEntity;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Test entity that does NOT implement IHistoryTrackable for testing unsupported entities.
 */
class TestNonTrackableEntity implements IEntity
{
    private UuidInterface $id;

    private string $name;

    public function __construct(
        ?UuidInterface $id = null,
        string $name = 'Non-trackable Entity',
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->name = $name;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
