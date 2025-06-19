<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\ILabelable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class TestCollectionEntity implements IEntity, ILabelable
{
    private UuidInterface $id;

    /**
     * @var non-empty-string
     */
    private string $name;

    /**
     * @var positive-int
     */
    private int $sortOrder;

    /**
     * @param non-empty-string $name
     * @param positive-int $sortOrder
     */
    public function __construct(string $name = 'Collection Item', int $sortOrder = 1)
    {
        $this->id = Uuid::uuid4();
        $this->name = $name;
        $this->sortOrder = $sortOrder;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param non-empty-string $name
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @param positive-int $sortOrder
     */
    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function getLabel(): string
    {
        return sprintf('#%d - %s', $this->sortOrder, $this->name);
    }
}
