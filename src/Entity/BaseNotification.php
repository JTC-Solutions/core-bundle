<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JtcSolutions\Core\Dto\EntityLink\IEntityLinkable;
use JtcSolutions\Core\Enum\NotificationImportance;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * This entity is used to store notification information, it should be extended by concrete implementation of the notification class.
 * Child entity must implement relation to BaseNotificationList entity as OneToMany.
 *
 * @template TNotificationList of BaseNotificationList
 */
abstract class BaseNotification implements IEntity, IEntityLinkable
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['notification:list', 'notification:detail'])]
    protected UuidInterface $id;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'string')]
    #[Groups(['notification:list', 'notification:detail'])]
    protected string $subject;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'string')]
    #[Groups(['notification:list', 'notification:detail'])]
    protected string $content;

    /**
     * @var class-string
     */
    #[ORM\Column(type: 'string')]
    protected string $subjectFQCN;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'string')]
    protected string $subjectId;

    /**
     * @var array<string, int|float|string|bool>
     */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    #[Groups(['notification:list', 'notification:detail'])]
    protected array $details;

    #[ORM\Column(type: 'string', enumType: NotificationImportance::class)]
    #[Groups(['notification:list', 'notification:detail'])]
    protected NotificationImportance $importance;

    /**
     * @param non-empty-string $subject
     * @param non-empty-string $content
     * @param class-string $subjectFQCN
     * @param non-empty-string $subjectId
     * @param array<string, int|float|string|bool> $details
     */
    public function __construct(
        UuidInterface $id,
        string $subject,
        string $content,
        string $subjectFQCN,
        string $subjectId,
        array $details,
        NotificationImportance $importance,
    ) {
        $this->id = $id;
        $this->subject = $subject;
        $this->content = $content;
        $this->subjectFQCN = $subjectFQCN;
        $this->subjectId = $subjectId;
        $this->details = $details;
        $this->importance = $importance;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    /**
     * @return non-empty-string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @param non-empty-string $subject
     */
    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    /**
     * @return non-empty-string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param non-empty-string $content
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * @return class-string
     */
    public function getSubjectFQCN(): string
    {
        return $this->subjectFQCN;
    }

    /**
     * @param class-string $subjectFQCN
     */
    public function setSubjectFQCN(string $subjectFQCN): void
    {
        $this->subjectFQCN = $subjectFQCN;
    }

    /**
     * @return non-empty-string
     */
    public function getSubjectId(): string
    {
        return $this->subjectId;
    }

    /**
     * @param non-empty-string $subjectId
     */
    public function setSubjectId(string $subjectId): void
    {
        $this->subjectId = $subjectId;
    }

    /**
     * @return array<string, int|float|string|bool>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * @param array<string, int|float|string|bool> $details
     */
    public function setDetails(array $details): void
    {
        $this->details = $details;
    }

    public function getImportance(): NotificationImportance
    {
        return $this->importance;
    }

    public function setImportance(NotificationImportance $importance): void
    {
        $this->importance = $importance;
    }

    /**
     * @return Collection<int, TNotificationList>
     */
    abstract public function getUsers(): Collection;
}
