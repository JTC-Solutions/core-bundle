<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use JtcSolutions\Core\Entity\BaseHistory;
use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test history entity for TestItem tracking.
 */
class TestItemHistory extends BaseHistory implements IHistory
{
    private TestItem $item;

    public function __construct(
        UuidInterface $id,
        ?UserInterface $createdBy,
        ?string $message,
        HistorySeverityEnum $severity,
        array $changes,
        TestItem $item,
    ) {
        parent::__construct($id, $createdBy, $message, $severity, $changes);
        $this->item = $item;
    }

    public function getItem(): TestItem
    {
        return $this->item;
    }
}
