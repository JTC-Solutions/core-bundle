<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto;

/**
 * Data Transfer Object representing a pivot entity change in history.
 *
 * Extends HistoryChange to include pivot-specific information such as
 * the pivot entity type and additional pivot data. This allows tracking
 * of complex Many-to-Many relationships with custom pivot entities that
 * contain more than just foreign keys.
 */
final readonly class PivotHistoryChange extends HistoryChange
{
    /**
     * @param non-empty-string $field Field name that changed (relationship type)
     * @param non-empty-string $type Type of change (pivot_created, pivot_updated, pivot_deleted)
     * @param mixed $from Previous value with pivot data
     * @param mixed $to New value with pivot data
     * @param string|null $translationKey Key for translating field name in UI
     * @param non-empty-string $entityType Short class name of the target entity
     * @param non-empty-string $pivotEntityType Short class name of the pivot entity
     * @param array<string, mixed>|null $pivotData Additional pivot-specific data
     */
    public function __construct(
        string $field,
        string $type,
        mixed $from,
        mixed $to,
        ?string $translationKey,
        string $entityType,
        public string $pivotEntityType,
        public ?array $pivotData = null,
    ) {
        parent::__construct($field, $type, $from, $to, $translationKey, $entityType);
    }
}
