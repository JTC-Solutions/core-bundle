<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Parser\BaseDoctrineEventParser;

/**
 * Test fixture parser that includes pivot entity tracking support.
 * Demonstrates how to implement pivot entity tracking in a concrete parser.
 */
class TestPivotDoctrineEventParser extends BaseDoctrineEventParser
{
    public function getDefinedPivotEntities(IHistoryTrackable $entity): array
    {
        if ($entity instanceof TestUser) {
            return [
                'role' => TestPivotEntity::class,
            ];
        }

        if ($entity instanceof TestRole) {
            return [
                'user' => TestPivotEntity::class,
            ];
        }

        return [];
    }

    protected function getDefinedCollections(IHistoryTrackable $entity): array
    {
        if ($entity instanceof TestUser) {
            return [
                'items' => $entity->getItems(),
                'relatedEntities' => $entity->getRelatedEntities(),
            ];
        }

        if ($entity instanceof TestRole) {
            // TestRole doesn't have collections in our test setup
            return [];
        }

        return [];
    }

    protected function getDefinedEnums(): array
    {
        return [
            'status' => TestStatusEnum::class,
        ];
    }
}
