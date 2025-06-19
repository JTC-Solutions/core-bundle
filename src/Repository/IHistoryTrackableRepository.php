<?php

namespace JtcSolutions\Core\Repository;

use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Entity\IHistoryTrackable;

interface IHistoryTrackableRepository
{
    /** @return IHistory[] */
    public function getHistoryByEntity(IHistoryTrackable $entity): array;
}