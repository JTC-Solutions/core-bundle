<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\Dummy\Repository;

use Doctrine\ORM\EntityManagerInterface;
use JtcSolutions\Core\Repository\BaseRepository;
use JtcSolutions\Core\Repository\IEntityRepository;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Entity\AnotherDummyEntity;

/**
 * @extends BaseRepository<AnotherDummyEntity>
 * @implements IEntityRepository<AnotherDummyEntity>
 */
class AnotherDummyRepository extends BaseRepository implements IEntityRepository
{
    public function __construct(
        EntityManagerInterface $registry,
    ) {
        parent::__construct($registry, AnotherDummyEntity::class);
    }
}
