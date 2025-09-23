<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto\Notification;

readonly class NotificationGroup
{
    /**
     * @param non-empty-string $type
     * @param int<0, max> $total
     * @param NotificationView[] $data
     */
    public function __construct(
        public string $type,
        public int $total,
        public array $data,
    ) {
    }
}
