<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto\Notification;

use JtcSolutions\Core\Dto\Pagination;

readonly class NotificationResponse
{
    /**
     * @param NotificationView[] $data
     */
    public function __construct(
        public array $data,
        public Pagination $metadata,
    ) {
    }
}
