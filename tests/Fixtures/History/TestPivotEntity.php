<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use DateTime;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Entity\IPivotHistoryTrackable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Test fixture for a pivot entity implementing IPivotHistoryTrackable.
 * Represents a many-to-many relationship between TestUser and TestRole entities
 * with additional pivot-specific data like permissions and granted date.
 */
class TestPivotEntity implements IPivotHistoryTrackable
{
    private UuidInterface $id;

    private TestUser $user;

    private TestRole $role;

    private array $permissions;

    private DateTime $grantedAt;

    public function __construct(
        TestUser $user,
        TestRole $role,
        array $permissions = [],
        ?DateTime $grantedAt = null,
    ) {
        $this->id = Uuid::uuid4();
        $this->user = $user;
        $this->role = $role;
        $this->permissions = $permissions;
        $this->grantedAt = $grantedAt ?? new DateTime();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getUser(): TestUser
    {
        return $this->user;
    }

    public function getRole(): TestRole
    {
        return $this->role;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function getGrantedAt(): DateTime
    {
        return $this->grantedAt;
    }

    public function setGrantedAt(DateTime $grantedAt): void
    {
        $this->grantedAt = $grantedAt;
    }

    public function getHistoryOwner(): IHistoryTrackable
    {
        return $this->user;
    }

    public function getHistoryTarget(): IEntity
    {
        return $this->role;
    }

    public function getRelationshipType(): string
    {
        return 'role';
    }

    public function getPivotData(): array
    {
        return [
            'permissions' => $this->permissions,
            'grantedAt' => $this->grantedAt->format('Y-m-d H:i:s'),
        ];
    }
}
