<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto\Notification;

use JtcSolutions\Core\Dto\Pagination;

readonly class NotificationGroupedResponse
{
    /**
     * @param NotificationGroup[] $data
     */
    public function __construct(
        public array $data,
        public Pagination $metadata,
    ) {
    }
}
