<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Extractor\BaseChangeExtractor;

/**
 * Test fixture change extractor for pivot entity testing.
 * Uses TestPivotDoctrineEventParser for parsing pivot entity changes.
 */
class TestPivotChangeExtractor extends BaseChangeExtractor
{
    public function supports(IHistoryTrackable $entity): bool
    {
        return $entity instanceof TestUser;
    }
}
