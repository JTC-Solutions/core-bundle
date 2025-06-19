<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JtcSolutions\Core\Dto\HistoryChange;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\MappedSuperclass]
abstract class BaseHistory implements IEntity, IHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['history:list', 'history:detail'])]
    private UuidInterface $id;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['history:list', 'history:detail'])]
    private ?string $message = null;

    #[ORM\Column(type: 'string', enumType: HistorySeverityEnum::class)]
    #[Groups(['history:list', 'history:detail'])]
    private HistorySeverityEnum $severity;

    /**
     * @var HistoryChange[]
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['history:list', 'history:detail'])]
    private array $changes = [];

    /**
     * @param HistoryChange[] $changes
     */
    public function __construct(
        UuidInterface $id,
        ?string $message,
        HistorySeverityEnum $severity,
        array $changes,
    ) {
        $this->id = $id;
        $this->message = $message;
        $this->severity = $severity;
        $this->changes = $changes;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getMessage(): ?string
    {
        return $this->message === '' ? null : $this->message;
    }

    public function getSeverity(): HistorySeverityEnum
    {
        return $this->severity;
    }

    /** @return HistoryChange[] */
    public function getChanges(): array
    {
        return $this->changes;
    }

    abstract public function getCreatedBy(): UserInterface;

    abstract public function getCreatedAt(): DateTimeImmutable;
}
