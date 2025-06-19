<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Factory;

use Doctrine\ORM\EntityManagerInterface;
use JtcSolutions\Core\Dto\HistoryChange;
use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Enum\HistoryActionTypeEnum;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use JtcSolutions\Helpers\Helper\FQCNHelper;
use RuntimeException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class BaseHistoryFactory implements IHistoryFactory
{
    /**
     * Defines for what entity this factory is created.
     * If User has History, then the className constant is FQCN of User Entity.
     *
     * @var class-string<IHistoryTrackable>|string
     */
    protected const string CLASS_NAME = '';

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly TranslatorInterface $translator,
    ) {
        if (static::CLASS_NAME === '') {
            throw new RuntimeException('Class name must be set in child class');
        }
    }

    abstract public function supports(IHistoryTrackable $entity): bool;

    /**
     * Creates history entry from entity creation event.
     *
     * @param array{id: non-empty-string, label: string|null} $change
     */
    public function createFromCreate(
        ?UserInterface $createdBy,
        IHistoryTrackable $entity,
        array $change,
    ): IHistory {
        // Simple format for regular entities - just entity reference with id and label
        $entityType = FQCNHelper::transformFQCNToShortClassName(static::CLASS_NAME);
        assert($entityType !== '');
        $changeDto = new HistoryChange(
            field: 'entity',
            type: HistoryActionTypeEnum::CREATE->value,
            from: null,
            to: $change,
            translationKey: null,
            entityType: $entityType,
        );

        $record = $this->createHistoryEntity(
            user: $createdBy,
            message: null,
            severity: HistorySeverityEnum::LOW,
            changes: [$changeDto],
            entity: $entity,
        );

        $this->entityManager->persist($record);
        $this->entityManager->flush(); // in create call persist

        return $record;
    }

    /**
     * Creates history entry from entity update event.
     *
     * @param array<int, $createdBy array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     actionType: HistoryActionTypeEnum,
     *     relatedEntity?: string|null,
     *     enumName?: non-empty-string
     * }> $changes
     */
    public function createFromUpdate(
        ?UserInterface $createdBy,
        IHistoryTrackable $entity,
        array $changes,
    ): IHistory {
        $changeDtos = [];
        foreach ($changes as $change) {
            if (isset($change['enumName'])) {
                $changeDtos[] = $this->createEnumChangeDto($change, $change['enumName']);
                continue;
            }
            $changeDtos[] = $this->createChangeDto($change);
        }

        $record = $this->createHistoryEntity(
            user: $createdBy,
            message: null,
            severity: HistorySeverityEnum::LOW,
            changes: $changeDtos,
            entity: $entity,
        );

        $this->entityManager->persist($record);
        // do not call flush to prevent cycling

        return $record;
    }

    /**
     * @param array<int, HistoryChange> $changes
     */
    abstract protected function createHistoryEntity(
        ?UserInterface $user,
        ?string $message,
        HistorySeverityEnum $severity,
        array $changes,
        IHistoryTrackable $entity,
    ): IHistory;

    /**
     * @param array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     actionType: HistoryActionTypeEnum,
     *     relatedEntity?: string|null
     * } $changeData
     */
    private function createChangeDto(array $changeData): HistoryChange
    {
        $entityType = $changeData['relatedEntity'] ?? null ?: FQCNHelper::transformFQCNToShortClassName(static::CLASS_NAME);
        assert($entityType !== '');
        return new HistoryChange(
            field: $changeData['field'],
            type: $changeData['actionType']->value,
            from: $changeData['oldValue'],
            to: $changeData['newValue'],
            translationKey: $changeData['field'],
            entityType: $entityType,
        );
    }

    /**
     * @param array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     actionType: HistoryActionTypeEnum,
     *     relatedEntity?: string|null,
     *     enumName: non-empty-string
     * } $changeData
     */
    private function createEnumChangeDto(array $changeData, string $enumName): HistoryChange
    {
        $labelTemplate = "{$enumName}.%s";

        $newValue = $changeData['newValue'];
        $oldValue = $changeData['oldValue'];

        $entityType = $changeData['relatedEntity'] ?? null ?: FQCNHelper::transformFQCNToShortClassName(static::CLASS_NAME);
        assert($entityType !== '');

        return new HistoryChange(
            field: $changeData['field'],
            type: $changeData['actionType']->value,
            from: [
                'value' => $oldValue,
                'label' => $oldValue !== null ? sprintf($labelTemplate, is_scalar($oldValue) ? (string) $oldValue : 'unknown') : null,
                'type' => 'enum',
            ],
            to: [
                'value' => $newValue,
                'label' => $newValue !== null ? sprintf($labelTemplate, is_scalar($newValue) ? (string) $newValue : 'unknown') : null,
                'type' => 'enum',
            ],
            translationKey: $changeData['field'] . '.label',
            entityType: $entityType,
        );
    }
}
