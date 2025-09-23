<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Factory;

use JtcSolutions\Core\Dto\Pagination;

class PaginationFactory
{
    /**
     * @param int<0, max> $total
     * @param int<0, max> $offset
     * @param int<1, max> $limit
     */
    public static function create(int $total, int $offset, int $limit): Pagination
    {
        /** @var int<0, max> $totalPages */
        $totalPages = (int) ceil($total / $limit);
        /** @var int<1, max> $page */
        $page = (int) floor($offset / $limit) + 1;

        return new Pagination(
            totalItems: $total,
            totalPages: $totalPages,
            itemsPerPage: $limit,
            page: $page,
        );
    }
}
