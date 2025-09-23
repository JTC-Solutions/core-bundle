<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Message;

use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Enum\NotificationImportance;

readonly class NotificationMessage
{
    /**
     * @param non-empty-string $subject
     * @param non-empty-string $content
     * @param IEntity[]|null $watchers if watchers are null, it means that the notification is sent to all users
     * @param array<string, float|int|string|bool> $details
     */
    public function __construct(
        public IEntity $subjectEntity,
        public string $subject,
        public string $content,
        public NotificationImportance $importance,
        public array $details = [],
        public ?array $watchers = null,
    ) {
    }
}
