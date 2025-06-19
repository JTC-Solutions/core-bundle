<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Entity\ILabelable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Test fixture for a role entity used in pivot relationship testing.
 * Implements ILabelable to provide human-readable labels for history entries.
 */
class TestRole implements IEntity, IHistoryTrackable, ILabelable
{
    private UuidInterface $id;

    private string $name;

    private string $description;

    public function __construct(string $name, string $description = '')
    {
        $this->id = Uuid::uuid4();
        $this->name = $name;
        $this->description = $description;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getLabel(): string
    {
        return $this->name;
    }

    public function getHistoryEntityFQCN(): string
    {
        return TestRoleHistory::class;
    }
}
