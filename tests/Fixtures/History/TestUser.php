<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Entity\ILabelable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Test User entity with realistic fields for comprehensive testing.
 * Implements IHistoryTrackable and ILabelable for history tracking.
 */
class TestUser implements IHistoryTrackable, ILabelable
{
    private UuidInterface $id;

    private string $firstname;

    private string $lastname;

    private string $email;

    private DateTimeInterface $createdAt;

    private bool $isActive;

    private ?TestUser $createdBy;

    /**
     * @var Collection<int, TestItem>
     */
    private Collection $items;

    public function __construct(
        ?UuidInterface $id = null,
        string $firstname = 'John',
        string $lastname = 'Doe',
        string $email = 'john.doe@example.com',
        ?DateTimeInterface $createdAt = null,
        bool $isActive = true,
        ?self $createdBy = null,
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->firstname = $firstname;
        $this->lastname = $lastname;
        $this->email = $email;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->isActive = $isActive;
        $this->createdBy = $createdBy;
        $this->items = new ArrayCollection();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): void
    {
        $this->firstname = $firstname;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): void
    {
        $this->lastname = $lastname;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCreatedBy(): ?self
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?self $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    /**
     * @return Collection<int, TestItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(TestItem $item): void
    {
        if (! $this->items->contains($item)) {
            $this->items->add($item);
            $item->setOwner($this);
        }
    }

    public function removeItem(TestItem $item): void
    {
        if ($this->items->contains($item)) {
            $this->items->removeElement($item);
            $item->setOwner(null);
        }
    }

    public function getLabel(): string
    {
        return "{$this->firstname} {$this->lastname} ({$this->email})";
    }

    public function getHistoryEntityFQCN(): string
    {
        return TestUserHistory::class;
    }

    public function getFullName(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }
}
