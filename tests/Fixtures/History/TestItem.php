<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Entity\ILabelable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Test Item entity with realistic fields for comprehensive testing.
 * Represents items that belong to users in a collection relationship.
 */
class TestItem implements IEntity, IHistoryTrackable, ILabelable
{
    private UuidInterface $id;

    private string $name;

    private int $quantity;

    private float $price;

    private ?TestUser $owner;

    public function __construct(
        ?UuidInterface $id = null,
        string $name = 'Test Item',
        int $quantity = 1,
        float $price = 10.99,
        ?TestUser $owner = null,
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->name = $name;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->owner = $owner;
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getOwner(): ?TestUser
    {
        return $this->owner;
    }

    public function setOwner(?TestUser $owner): void
    {
        $this->owner = $owner;
    }

    public function getLabel(): string
    {
        return "{$this->name} (Qty: {$this->quantity}, Price: €{$this->price})";
    }

    public function getTotalValue(): float
    {
        return $this->quantity * $this->price;
    }

    public function getHistoryEntityFQCN(): string
    {
        return TestItemHistory::class;
    }
}
