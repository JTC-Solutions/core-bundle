<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Factory;

use JtcSolutions\Core\Dto\EntityLink\EntityLink;
use JtcSolutions\Core\Dto\EntityLink\IEntityLinkable;
use JtcSolutions\Helpers\Helper\FQCNHelper;
use JtcSolutions\Helpers\Helper\StringUtils;
use Ramsey\Uuid\Uuid;

/**
 * Generates a {@link EntityLink} instance based on the provided {@link IEntityLinkable} object.
 * Properties of this factory are:
 *  - $notificationUrlList: A map of entity types for their corresponding notification URLs.
 *  - $defaultUrlPattern: A default URL pattern for entities without specific URLs.
 */
class EntityLinkFactory
{
    /**
     * @param array<string, string> $notificationUrlList
     * @param non-empty-string $defaultUrlPattern
     */
    public function __construct(
        protected readonly array $notificationUrlList,
        protected readonly string $defaultUrlPattern,
    ) {
    }

    public function create(IEntityLinkable $linkable): EntityLink
    {
        $id = Uuid::fromString($linkable->getSubjectId());
        $type = FQCNHelper::transformFQCNToShortClassName($linkable->getSubjectFQCN());

        if (isset($this->notificationUrlList[$type])) {
            $detailUrl = sprintf($this->notificationUrlList[$type], $linkable->getSubjectId());
        } else {
            $snakeCaseType = StringUtils::toSnakeCase($type);
            $url = str_replace('{subject}', $snakeCaseType, $this->defaultUrlPattern);
            $detailUrl = str_replace('{id}', $linkable->getSubjectId(), $url);
        }

        return new EntityLink(
            url: $detailUrl,
            type: $type,
            id: $id,
        );
    }
}
