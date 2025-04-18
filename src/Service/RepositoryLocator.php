<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Service;

use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Repository\IEntityRepository;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class RepositoryLocator
{
    /**
     * @param iterable<IEntityRepository<IEntity>> $repositories
     */
    public function __construct(
        #[AutowireIterator('doctrine.repository_service')]
        private readonly iterable $repositories,
    ) {
    }

    /**
     * @param class-string $entityFQCN
     * @return IEntityRepository<IEntity>
     */
    public function locate(string $entityFQCN): IEntityRepository
    {
        foreach ($this->repositories as $repository) {
            if ($repository->getEntityName() === $entityFQCN) {
                return $repository;
            }
        }

        throw new RuntimeException('Repository not found for entity: ' . $entityFQCN);
    }
}
