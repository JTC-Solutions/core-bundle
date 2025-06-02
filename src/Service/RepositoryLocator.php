<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Service;

use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Repository\IEntityRepository;
use JtcSolutions\Helpers\Helper\FQCNHelper;
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

    /**
     * Finds and returns the repository associated with the given entity short name.
     *
     * Iterates through the injected repositories and compares the short name (class name without namespace)
     * of their managed entity with the provided short name.
     *
     * @param string $entityShortName The short name (without namespace) of the entity whose repository is needed.
     * @return IEntityRepository<IEntity> The repository instance managing the specified entity.
     * @throws RuntimeException If no repository is found for the given entity short name.
     */
    public function locateByShortName(string $entityShortName): IEntityRepository
    {
        foreach ($this->repositories as $repository) {
            $entityFQCN = $repository->getEntityName();
            $shortName = FQCNHelper::transformFQCNToShortClassName($entityFQCN);

            if ($shortName === $entityShortName) {
                /** @var IEntityRepository<IEntity> $repository */
                return $repository;
            }
        }

        throw new RuntimeException('Repository not found for entity short name: ' . $entityShortName);
    }
}
