<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto\Notification;

use DateTimeImmutable;
use JtcSolutions\Core\Dto\EntityLink\EntityLink;
use JtcSolutions\Core\Enum\NotificationImportance;
use Ramsey\Uuid\UuidInterface;

readonly class NotificationView
{
    public function __construct(
        public UuidInterface $id,
        public string $subject,
        public string $content,
        public NotificationImportance $importance,
        public EntityLink $link,
        public ?DateTimeImmutable $readAt = null,
    ) {
    }
}
