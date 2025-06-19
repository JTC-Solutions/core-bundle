<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto;

/**
 * Data Transfer Object representing a single change in entity history.
 * Contains structured information about what field changed, its old/new values,
 * and metadata for translation and display purposes.
 */
final readonly class HistoryChange
{
    /**
     * @param non-empty-string $field Field name that changed
     * @param non-empty-string $type Type of change (create, update, delete, etc.)
     * @param mixed $from Previous value (can be scalar, array, or null)
     * @param mixed $to New value (can be scalar, array, or null)
     * @param string|null $translationKey Key for translating field name in UI
     * @param non-empty-string $entityType Short class name of the related entity
     */
    public function __construct(
        public string $field,
        public string $type,
        public mixed $from,
        public mixed $to,
        public ?string $translationKey,
        public string $entityType,
    ) {
    }
}
