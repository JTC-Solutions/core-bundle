<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Represents pagination metadata typically included in responses for lists of items.
 * Contains information about the total number of items, pages, items per page, and the current page.
 * Properties are marked with the 'reference' serialization group.
 */
readonly class Pagination
{
    /**
     * @param int<0, max> $totalItems The total number of items available across all pages.
     * @param int<0, max> $totalPages The total number of pages calculated based on totalItems and itemsPerPage.
     * @param int<0, max> $itemsPerPage The maximum number of items included on a single page.
     * @param int<0, max> $page The current page number (usually 1-based).
     */
    public function __construct(
        #[Groups(['reference'])]
        public int $totalItems,
        #[Groups(['reference'])]
        public int $totalPages,
        #[Groups(['reference'])]
        public int $itemsPerPage,
        #[Groups(['reference'])]
        public int $page,
    ) {
    }
}
