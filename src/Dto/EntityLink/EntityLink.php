<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto\EntityLink;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;

/** This class is created from the IEntityLinkable interface and is used to generate a simple url link. */
readonly class EntityLink
{
    /**
     * @param non-empty-string $type
     */
    public function __construct(
        #[Groups(['reference'])]
        public string $url,
        #[Groups(['reference'])]
        public string $type,
        #[Groups(['reference'])]
        public UuidInterface $id,
    ) {
    }
}
