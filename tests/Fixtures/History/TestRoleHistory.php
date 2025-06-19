<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use DateTimeImmutable;
use JtcSolutions\Core\Dto\HistoryChange;
use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test Role History entity for comprehensive testing.
 * Represents history entries for Role entity changes.
 */
class TestRoleHistory implements IHistory
{
    private UuidInterface $id;

    private ?UserInterface $createdBy;

    private ?string $message;

    private HistorySeverityEnum $severity;

    /**
     * @var array<int, HistoryChange>
     */
    private array $changes;

    private TestRole $role;

    /**
     * @param array<int, HistoryChange> $changes
     */
    public function __construct(
        UuidInterface $id,
        ?UserInterface $createdBy,
        ?string $message,
        HistorySeverityEnum $severity,
        array $changes,
        TestRole $role,
    ) {
        $this->id = $id;
        $this->createdBy = $createdBy;
        $this->message = $message;
        $this->severity = $severity;
        $this->changes = $changes;
        $this->role = $role;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getCreatedBy(): ?UserInterface
    {
        return $this->createdBy;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getSeverity(): HistorySeverityEnum
    {
        return $this->severity;
    }

    /**
     * @return array<int, HistoryChange>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    public function getRole(): TestRole
    {
        return $this->role;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
