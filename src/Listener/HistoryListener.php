<?php

namespace JtcSolutions\Core\Listener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Exception\HistoryException;
use JtcSolutions\Core\Factory\IHistoryFactory;
use JtcSolutions\Core\Parser\IDoctrineEventParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\User\UserInterface;

class HistoryListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        #[AutowireIterator('app.change_extractor')]
        private readonly iterable $changeExtractors,
        #[AutowireIterator('app.history_factory')]
        private readonly iterable $historyFactories,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($this->isSupported($entity) === false) {
            return;
        }

        /** @var IHistoryTrackable $historyTrackableEntity */
        $historyTrackableEntity = $entity;

        /** @var UserInterface|null $user */
        $user = $this->security->getUser();

        $changeExtractor = $this->getChangeExtractor($historyTrackableEntity);
        $changes = $changeExtractor->extractCreationData($historyTrackableEntity);
        $this->getHistoryFactory($historyTrackableEntity)->createFromPersistEvent($user, $historyTrackableEntity, $changes);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {

    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {

    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {

    }

    /**
     * @throws HistoryException when Entity without IEntity interface provided
     * @return bool false if the entity does not implement IHistoryTrackable interface
     */
    private function isSupported(object $entity): bool
    {
        if ($entity instanceof IEntity === false) {
            throw HistoryException::listenerReceivedEntityWithoutEntityInterface($entity);
        }

        if ($entity instanceof IHistoryTrackable) {
            return true;
        }

        return false;
    }

    /**
     * Returns IHistoryFactory for a given entity with IHistoryTrackable interface.
     * Each entity should have its own factory.
     *
     * @throws HistoryException
     */
    protected function getHistoryFactory(IHistoryTrackable $entity): IHistoryFactory
    {
        foreach ($this->historyFactories as $historyFactory) {
            if ($historyFactory->supports($entity)) {
                return $historyFactory;
            }
        }

        throw HistoryException::factoryNotFound($entity::class);
    }

    /**
     * Returns IDoctrineEventParser for a given entity with IHistoryTrackable interface.
     *
     * @throws HistoryException
     */
    protected function getChangeExtractor(IHistoryTrackable $entity): IDoctrineEventParser
    {
        foreach ($this->changeExtractors as $changeExtractor) {
            if ($changeExtractor->supports($entity)) {
                return $changeExtractor;
            }
        }

        throw HistoryException::changeExtractorNotFound($entity::class);
    }
}