<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use JtcSolutions\Core\Dto\HistoryChange;
use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use JtcSolutions\Core\Factory\BaseHistoryFactory;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test fixture history factory for pivot entity testing.
 * Creates TestUserHistory entities that can handle pivot changes.
 */
class TestPivotHistoryFactory extends BaseHistoryFactory
{
    protected const string CLASS_NAME = TestUser::class;

    public function supports(IHistoryTrackable $entity): bool
    {
        return $entity instanceof TestUser;
    }

    /**
     * @param array<int, HistoryChange> $changes
     */
    protected function createHistoryEntity(
        ?UserInterface $user,
        ?string $message,
        HistorySeverityEnum $severity,
        array $changes,
        IHistoryTrackable $entity,
    ): IHistory {
        /** @var TestUser $entity */
        return new TestUserHistory(
            $entity->getId(),
            $user,
            $message,
            $severity,
            $changes,
            $entity,
        );
    }
}
