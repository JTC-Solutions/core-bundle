<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Listener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Exception\HistoryException;
use JtcSolutions\Core\Extractor\BaseChangeExtractor;
use JtcSolutions\Core\Factory\BaseHistoryFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Doctrine event listener that automatically tracks changes to entities implementing IHistoryTrackable.
 * Orchestrates the history tracking process by using extractors to parse changes
 * and factories to create history entries.
 */
class HistoryListener
{
    /**
     * @param BaseHistoryFactory[] $historyFactories
     * @param BaseChangeExtractor[] $changeExtractors
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        #[AutowireIterator('app.change_extractor')]
        private readonly iterable $changeExtractors,
        #[AutowireIterator('app.history_factory')]
        private readonly iterable $historyFactories,
    ) {
    }

    /**
     * Handles entity creation events.
     * Creates a history entry documenting the entity creation.
     *
     * @throws HistoryException
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (! $entity instanceof IHistoryTrackable) {
            return;
        }

        /** @var UserInterface|null $user */
        $user = $this->security->getUser();

        $changeExtractor = $this->getChangeExtractor($entity);
        $changes = $changeExtractor->extractCreationData($entity);
        $this->getHistoryFactory($entity)->createFromCreate($user, $entity, $changes);
    }

    /**
     * Handles entity update events (pre-update phase).
     * Extracts all types of changes and creates history entries.
     *
     * @throws HistoryException
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (! $entity instanceof IHistoryTrackable) {
            return;
        }

        $unitOfWork = $this->entityManager->getUnitOfWork();
        $changeExtractor = $this->getChangeExtractor($entity);

        /** @var array<non-empty-string, array{mixed, mixed}> $changeSet */
        $changeSet = $args->getEntityChangeSet();
        $changes = $changeExtractor->extractUpdateData($changeSet);
        $collectionChanges = $changeExtractor->extractCollectionUpdateData($entity, $unitOfWork->getScheduledCollectionUpdates());
        $collectionDeletions = $changeExtractor->extractCollectionDeleteData($entity, $unitOfWork->getScheduledCollectionDeletions());

        $allChanges = array_merge($changes, $collectionChanges, $collectionDeletions);

        // if no changes, just skip
        if ($allChanges === []) {
            return;
        }

        /** @var UserInterface|null $user */
        $user = $this->security->getUser();

        $this->getHistoryFactory($entity)->createFromUpdate($user, $entity, $allChanges);
    }

    /**
     * Handles post-update events.
     * Flushes any pending history entries to the database.
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->entityManager->flush();
    }

    /**
     * Finds the appropriate history factory for the given entity.
     *
     * @throws HistoryException When no factory supports the entity
     */
    protected function getHistoryFactory(IHistoryTrackable $entity): BaseHistoryFactory
    {
        foreach ($this->historyFactories as $historyFactory) {
            if ($historyFactory->supports($entity)) {
                return $historyFactory;
            }
        }

        throw HistoryException::factoryNotFound($entity::class);
    }

    /**
     * Finds the appropriate change extractor for the given entity.
     *
     * @throws HistoryException When no extractor supports the entity
     */
    protected function getChangeExtractor(IHistoryTrackable $entity): BaseChangeExtractor
    {
        foreach ($this->changeExtractors as $changeExtractor) {
            if ($changeExtractor->supports($entity)) {
                return $changeExtractor;
            }
        }

        throw HistoryException::changeExtractorNotFound($entity::class);
    }
}
