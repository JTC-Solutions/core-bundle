<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

readonly class Pagination
{
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
