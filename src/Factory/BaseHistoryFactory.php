<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Factory;

use Doctrine\ORM\EntityManagerInterface;
use JtcSolutions\Core\Dto\HistoryChange;
use JtcSolutions\Core\Dto\PivotHistoryChange;
use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Enum\HistoryActionTypeEnum;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use JtcSolutions\Helpers\Helper\FQCNHelper;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

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
        protected readonly LoggerInterface $logger = new NullLogger(),
    ) {
        if (static::CLASS_NAME === '') {
            throw new RuntimeException('Class name must be set in child class');
        }
    }

    abstract public function supports(IHistoryTrackable $entity): bool;

    /**
     * Creates history entry from an entity creation event.
     *
     * @param array{id: non-empty-string, label: string|null} $change
     */
    public function createFromCreate(
        ?UserInterface $createdBy,
        IHistoryTrackable $entity,
        array $change,
    ): IHistory {
        $startTime = microtime(true);
        $entityClass = $entity::class;
        $userId = $createdBy?->getUserIdentifier() ?? 'anonymous';

        $this->logger->info('History factory: Creating history entry for entity creation', [
            'entity_class' => $entityClass,
            'factory_class' => static::class,
            'user_id' => $userId,
        ]);

        try {
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

            $this->logger->debug('History factory: Change DTO created for entity creation', [
                'entity_class' => $entityClass,
                'entity_type' => $entityType,
                'change_data' => $change,
            ]);

            $historyCreateStartTime = microtime(true);
            $record = $this->createHistoryEntity(
                user: $createdBy,
                message: null,
                severity: HistorySeverityEnum::LOW,
                changes: [$changeDto],
                entity: $entity,
            );
            $historyCreateDuration = (microtime(true) - $historyCreateStartTime) * 1000;

            $this->logger->debug('History factory: History entity created', [
                'entity_class' => $entityClass,
                'history_entity_class' => $record::class,
                'creation_duration_ms' => round($historyCreateDuration, 2),
            ]);

            $persistStartTime = microtime(true);
            $this->entityManager->persist($record);
            $this->entityManager->flush(); // in create call persist
            $persistDuration = (microtime(true) - $persistStartTime) * 1000;

            $totalDuration = (microtime(true) - $startTime) * 1000;
            $this->logger->info('History factory: Entity creation history completed', [
                'entity_class' => $entityClass,
                'persist_duration_ms' => round($persistDuration, 2),
                'total_duration_ms' => round($totalDuration, 2),
            ]);

            return $record;
        } catch (Throwable $e) {
            $this->logger->error('History factory: Failed to create history for entity creation', [
                'entity_class' => $entityClass,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw $e; // Re-throw as this is a critical operation
        }
    }

    /**
     * Creates history entry from the entity update event.
     *
     * @param array<int, array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     actionType: HistoryActionTypeEnum,
     *     relatedEntity?: string|null,
     *     enumName?: non-empty-string,
     *     pivotEntity?: string
     * }> $changes
     */
    public function createFromUpdate(
        ?UserInterface $createdBy,
        IHistoryTrackable $entity,
        array $changes,
    ): IHistory {
        $startTime = microtime(true);
        $entityClass = $entity::class;
        $userId = $createdBy?->getUserIdentifier() ?? 'anonymous';

        $this->logger->info('History factory: Creating history entry for entity update', [
            'entity_class' => $entityClass,
            'factory_class' => static::class,
            'user_id' => $userId,
            'change_count' => count($changes),
        ]);

        try {
            $changeDtos = [];
            $pivotChanges = 0;
            $enumChanges = 0;
            $regularChanges = 0;

            $dtoCreateStartTime = microtime(true);
            foreach ($changes as $change) {
                if (array_key_exists('pivotEntity', $change)) {
                    /** @var array{field: non-empty-string, oldValue: mixed, newValue: mixed, actionType: HistoryActionTypeEnum, relatedEntity: string, pivotEntity: string} $change */
                    $changeDtos[] = $this->createPivotChangeDto($change);
                    $pivotChanges++;
                    continue;
                }
                if (isset($change['enumName'])) {
                    /** @var array{field: non-empty-string, oldValue: mixed, newValue: mixed, actionType: HistoryActionTypeEnum, relatedEntity?: string|null, enumName: non-empty-string} $change */
                    $changeDtos[] = $this->createEnumChangeDto($change, $change['enumName']);
                    $enumChanges++;
                    continue;
                }
                /** @var array{field: non-empty-string, oldValue: mixed, newValue: mixed, actionType: HistoryActionTypeEnum, relatedEntity?: string|null} $change */
                $changeDtos[] = $this->createChangeDto($change);
                $regularChanges++;
            }
            $dtoCreateDuration = (microtime(true) - $dtoCreateStartTime) * 1000;

            $this->logger->debug('History factory: Change DTOs created for entity update', [
                'entity_class' => $entityClass,
                'pivot_changes' => $pivotChanges,
                'enum_changes' => $enumChanges,
                'regular_changes' => $regularChanges,
                'total_changes' => count($changeDtos),
                'dto_creation_duration_ms' => round($dtoCreateDuration, 2),
            ]);

            $historyCreateStartTime = microtime(true);
            $record = $this->createHistoryEntity(
                user: $createdBy,
                message: null,
                severity: HistorySeverityEnum::LOW,
                changes: $changeDtos,
                entity: $entity,
            );
            $historyCreateDuration = (microtime(true) - $historyCreateStartTime) * 1000;

            $this->logger->debug('History factory: History entity created for update', [
                'entity_class' => $entityClass,
                'history_entity_class' => $record::class,
                'creation_duration_ms' => round($historyCreateDuration, 2),
            ]);

            $this->entityManager->persist($record);
            // do not call flush to prevent cycling

            $totalDuration = (microtime(true) - $startTime) * 1000;
            $this->logger->info('History factory: Entity update history completed', [
                'entity_class' => $entityClass,
                'total_changes' => count($changeDtos),
                'total_duration_ms' => round($totalDuration, 2),
            ]);

            return $record;
        } catch (Throwable $e) {
            $this->logger->error('History factory: Failed to create history for entity update', [
                'entity_class' => $entityClass,
                'change_count' => count($changes),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw $e; // Re-throw as this is a critical operation
        }
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

    /**
     * @param array{
     *     field: non-empty-string,
     *     oldValue: mixed,
     *     newValue: mixed,
     *     actionType: HistoryActionTypeEnum,
     *     relatedEntity: string,
     *     pivotEntity: string
     * } $changeData
     */
    private function createPivotChangeDto(array $changeData): PivotHistoryChange
    {
        $entityType = $changeData['relatedEntity'] ?: FQCNHelper::transformFQCNToShortClassName(static::CLASS_NAME);
        assert($entityType !== '');

        $pivotEntityType = $changeData['pivotEntity'];
        assert($pivotEntityType !== '');

        // Extract pivot data from the change data if present
        $pivotData = null;
        if (is_array($changeData['newValue']) && isset($changeData['newValue']['pivotData']) && is_array($changeData['newValue']['pivotData'])) {
            /** @var array<string, mixed> $pivotData */
            $pivotData = $changeData['newValue']['pivotData'];
        } elseif (is_array($changeData['oldValue']) && isset($changeData['oldValue']['pivotData']) && is_array($changeData['oldValue']['pivotData'])) {
            /** @var array<string, mixed> $pivotData */
            $pivotData = $changeData['oldValue']['pivotData'];
        }

        return new PivotHistoryChange(
            field: $changeData['field'],
            type: $changeData['actionType']->value,
            from: $changeData['oldValue'],
            to: $changeData['newValue'],
            translationKey: 'pivot.' . $changeData['field'] . '.' . strtolower(str_replace('pivot_', '', $changeData['actionType']->value)),
            entityType: $entityType,
            pivotEntityType: $pivotEntityType,
            pivotData: $pivotData,
        );
    }
}
