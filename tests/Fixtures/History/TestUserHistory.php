<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use JtcSolutions\Core\Dto\HistoryChange;
use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test User History entity for comprehensive testing.
 * Represents history entries for User entity changes.
 */
class TestUserHistory implements IHistory
{
    private UuidInterface $id;

    private ?UserInterface $createdBy;

    private ?string $message;

    private HistorySeverityEnum $severity;

    /**
     * @var array<int, HistoryChange>
     */
    private array $changes;

    private TestUser $user;

    /**
     * @param array<int, HistoryChange> $changes
     */
    public function __construct(
        UuidInterface $id,
        ?UserInterface $createdBy,
        ?string $message,
        HistorySeverityEnum $severity,
        array $changes,
        TestUser $user,
    ) {
        $this->id = $id;
        $this->createdBy = $createdBy;
        $this->message = $message;
        $this->severity = $severity;
        $this->changes = $changes;
        $this->user = $user;
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

    public function getUser(): TestUser
    {
        return $this->user;
    }
}
