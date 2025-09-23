<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * This entity serves as a parent ot relation (M:N) entity between notification and user entity.
 * Its child needs to extend it by providing user and notification properties.
 *
 * @template TNotification of BaseNotification
 * @template TUser of IUser
 */
abstract class BaseNotificationList implements IEntity
{
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['notification:list', 'notification:detail'])]
    public ?DateTimeImmutable $readAt = null;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['notification:list', 'notification:detail'])]
    private UuidInterface $id;

    public function __construct(
        UuidInterface $id,
        ?DateTimeImmutable $readAt = null,
    ) {
        $this->id = $id;
        $this->readAt = $readAt;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getReadAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?DateTimeImmutable $readAt): void
    {
        $this->readAt = $readAt;
    }

    /**
     * @return TNotification
     */
    abstract public function getNotification(): BaseNotification;

    /**
     * @param TNotification $notification
     */
    abstract public function setNotification(BaseNotification $notification): void;

    /**
     * @return TUser
     */
    abstract public function getUser(): IUser;

    /**
     * @param TUser $user
     */
    abstract public function setUser(IUser $user): void;
}
