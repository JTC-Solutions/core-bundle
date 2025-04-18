<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Service;

use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Repository\IEntityRepository;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Locates Doctrine entity repositories based on the entity's fully qualified class name (FQCN).
 *
 * This service leverages Symfony's dependency injection container to gather all services
 * tagged with 'doctrine.repository_service'. It provides a method to retrieve the correct
 * repository instance for a given entity class.
 */
class RepositoryLocator
{
    /**
     * @param iterable<IEntityRepository<IEntity>> $repositories An iterable collection of all repository services
     *                                                           automatically injected by Symfony DI via the
     *                                                           'doctrine.repository_service' tag.
     */
    public function __construct(
        #[AutowireIterator('doctrine.repository_service')]
        private readonly iterable $repositories,
    ) {
    }

    /**
     * Finds and returns the repository associated with the given entity FQCN.
     *
     * Iterates through the injected repositories and compares their managed entity name
     * with the provided FQCN.
     *
     * @template TEntity of IEntity
     * @param class-string<TEntity> $entityFQCN The fully qualified class name of the entity whose repository is needed.
     * @return IEntityRepository<TEntity> The repository instance managing the specified entity.
     * @throws RuntimeException If no repository is found for the given entity FQCN.
     */
    public function locate(string $entityFQCN): IEntityRepository
    {
        foreach ($this->repositories as $repository) {
            if ($repository->getEntityName() === $entityFQCN) {
                /** @var IEntityRepository<TEntity> $repository */
                return $repository;
            }
        }

        throw new RuntimeException('Repository not found for entity: ' . $entityFQCN);
    }
}
