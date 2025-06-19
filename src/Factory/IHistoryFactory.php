<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Factory;

use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Enum\HistoryActionTypeEnum;
use Symfony\Component\Security\Core\User\UserInterface;

interface IHistoryFactory
{
    /**
     * @param array{id: non-empty-string, label: string|null} $change
     */
    public function createFromCreate(
        ?UserInterface $createdBy,
        IHistoryTrackable $entity,
        array $change,
    ): IHistory;

    /**
     * @param UserInterface|null $createdBy
     * @param IHistoryTrackable $entity
     * @param array<int, array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     actionType: HistoryActionTypeEnum,
     *     relatedEntity?: string|null,
     *     enumName?: non-empty-string }> $changes
     * @return IHistory
     */
    public function createFromUpdate(
        ?UserInterface $createdBy,
        IHistoryTrackable $entity,
        array $changes,
    ): IHistory;

    public function supports(IHistoryTrackable $entity): bool;
}
