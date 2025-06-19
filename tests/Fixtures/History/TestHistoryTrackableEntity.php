<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Entity\ILabelable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class TestHistoryTrackableEntity implements IHistoryTrackable, ILabelable
{
    private UuidInterface $id;

    /**
     * @var non-empty-string
     */
    private string $name;

    /**
     * @var positive-int
     */
    private int $quantity;

    private float $price;

    private bool $isActive;

    private ?string $description;

    private DateTimeInterface $createdAt;

    private ?TestRelatedEntity $relatedEntity;

    private TestStatusEnum $status;

    /**
     * @var Collection<int, TestCollectionEntity>
     */
    private Collection $items;

    /**
     * @var Collection<int, TestRelatedEntity>
     */
    private Collection $relatedEntities;

    /**
     * @var array<string, mixed>
     */
    private array $metadata;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->items = new ArrayCollection();
        $this->relatedEntities = new ArrayCollection();
        $this->metadata = [];
        $this->isActive = true;
        $this->quantity = 1;
        $this->price = 0.0;
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @param positive-int $quantity
     */
    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getRelatedEntity(): ?TestRelatedEntity
    {
        return $this->relatedEntity;
    }

    public function setRelatedEntity(?TestRelatedEntity $relatedEntity): self
    {
        $this->relatedEntity = $relatedEntity;
        return $this;
    }

    public function getStatus(): TestStatusEnum
    {
        return $this->status;
    }

    public function setStatus(TestStatusEnum $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return Collection<int, TestCollectionEntity>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @param Collection<int, TestCollectionEntity> $items
     */
    public function setItems(Collection $items): self
    {
        $this->items = $items;
        return $this;
    }

    public function addItem(TestCollectionEntity $item): self
    {
        if (! $this->items->contains($item)) {
            $this->items->add($item);
        }
        return $this;
    }

    public function removeItem(TestCollectionEntity $item): self
    {
        $this->items->removeElement($item);
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getLabel(): string
    {
        return $this->name;
    }

    public function getHistoryEntityFQCN(): string
    {
        return TestHistory::class;
    }

    /**
     * @return Collection<int, TestRelatedEntity>
     */
    public function getRelatedEntities(): Collection
    {
        return $this->relatedEntities;
    }

    /**
     * @param Collection<int, TestRelatedEntity> $relatedEntities
     */
    public function setRelatedEntities(Collection $relatedEntities): self
    {
        $this->relatedEntities = $relatedEntities;
        return $this;
    }
}
