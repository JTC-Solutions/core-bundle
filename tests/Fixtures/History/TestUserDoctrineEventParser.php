<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use Doctrine\Common\Collections\Collection;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Parser\BaseDoctrineEventParser;

/**
 * Test User Doctrine Event Parser for comprehensive testing.
 * Defines collections and enums for User entity history tracking.
 */
class TestUserDoctrineEventParser extends BaseDoctrineEventParser
{
    public function __construct(array $ignoredFields = [])
    {
        parent::__construct($ignoredFields);
    }

    // Make protected methods public for testing
    public function parseEntityReference(IEntity|null $entity): ?array
    {
        return parent::parseEntityReference($entity);
    }

    public function parseScalarValue(mixed $value): string|null
    {
        return parent::parseScalarValue($value);
    }

    public function areValuesTheSame(mixed $oldValue, mixed $newValue): bool
    {
        return parent::areValuesTheSame($oldValue, $newValue);
    }

    public function isAnyValueEntityReference(array $values): bool
    {
        return parent::isAnyValueEntityReference($values);
    }

    public function getDefinedPivotEntities(IHistoryTrackable $entity): array
    {
        return [];
    }

    /**
     * @return array<string, Collection<int, \JtcSolutions\Core\Entity\IEntity>>
     */
    protected function getDefinedCollections(IHistoryTrackable $entity): array
    {
        if (! $entity instanceof TestUser) {
            return [];
        }

        return [
            'items' => $entity->getItems(),
        ];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getDefinedEnums(): array
    {
        return [
            'status' => TestStatusEnum::class,
        ];
    }
}
