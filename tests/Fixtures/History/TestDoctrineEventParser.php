<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use Doctrine\Common\Collections\Collection;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Parser\BaseDoctrineEventParser;

class TestDoctrineEventParser extends BaseDoctrineEventParser
{
    /**
     * @return array<string, Collection<int, IEntity>>
     */
    protected function getDefinedCollections(IHistoryTrackable $entity): array
    {
        if ($entity instanceof TestHistoryTrackableEntity) {
            /** @var array<string, Collection<int, IEntity>> */
            return [
                'items' => $entity->getItems(),
            ];
        }

        return [];
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
