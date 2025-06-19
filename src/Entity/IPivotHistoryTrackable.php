<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Entity;

/**
 * Marker interface for pivot entities that should have their lifecycle tracked in history.
 *
 * Pivot entities are intermediate entities in Many-to-Many relationships that contain
 * additional attributes beyond just foreign keys. For example, a UserRole entity
 * that stores not just user_id and role_id, but also permissions, granted_at, etc.
 *
 * When a pivot entity implements this interface, the history system will automatically
 * track its creation, updates, and deletion as part of the "owner" entity's history.
 */
interface IPivotHistoryTrackable extends IEntity
{
    /**
     * Returns the "owner" entity of this pivot relationship.
     * This determines which entity's history will contain the pivot changes.
     *
     * For example, in a UserRole pivot, the User would typically be the owner,
     * so UserRole changes appear in the User's history.
     *
     * @return IHistoryTrackable The entity that "owns" this relationship
     */
    public function getHistoryOwner(): IHistoryTrackable;

    /**
     * Returns the "target" entity of this pivot relationship.
     * This is the other entity in the M:N relationship.
     *
     * For example, in a UserRole pivot, the Role would be the target entity.
     *
     * @return IEntity The related entity in the M:N relationship
     */
    public function getHistoryTarget(): IEntity;

    /**
     * Returns human-readable description of the relationship type.
     * Used for generating history messages and translation keys.
     *
     * Examples: "role", "permission", "assignment", "membership"
     *
     * @return non-empty-string Relationship type identifier
     */
    public function getRelationshipType(): string;

    /**
     * Returns array of pivot-specific data for history tracking.
     * Only includes additional attributes, not the foreign keys to owner/target.
     *
     * For example, in UserRole: ['permissions' => ['read', 'write'], 'grantedAt' => '2024-06-19 14:30:00']
     * Foreign keys (user_id, role_id) are excluded as they're captured via getHistoryOwner/getHistoryTarget.
     *
     * @return array<string, mixed> Additional pivot data to track in history
     */
    public function getPivotData(): array;
}
