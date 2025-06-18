<?php

namespace JtcSolutions\Core\Factory;

use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use Symfony\Component\Security\Core\User\UserInterface;

interface IHistoryFactory
{
    public function createFromPersistEvent(
        UserInterface $user,
        IHistoryTrackable $historyTrackableEntity,
        array $changes,
    ): IHistory;

    public function createFromUpdateEvent(
        UserInterface $user,
        IHistoryTrackable $historyTrackableEntity,
        array $changes,
    ): IHistory;

    public function supports(IHistoryTrackable $historyTrackableEntity): bool;
}