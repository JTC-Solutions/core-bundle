<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Repository;

use JtcSolutions\Core\Entity\IEntity;

/** @template T of IEntity */
interface INotificationListRepository
{
    /**
     * @param int<1, max> $limit
     * @param int<0, max> $offset
     *
     * @return T[]
     */
    public function findByUser(IEntity $user, int $limit, int $offset): array;

    /**
     * @return int<0, max>
     */
    public function countByUser(IEntity $user): int;
}
