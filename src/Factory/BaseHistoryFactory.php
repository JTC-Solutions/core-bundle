<?php

namespace JtcSolutions\Core\Factory;

use Doctrine\ORM\EntityManagerInterface;
use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use JtcSolutions\Core\Exception\HistoryException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class BaseHistoryFactory
{
    /**
     * Defines for what entity this factory is created.
     * If User has History, then the className constant is FQCN of User Entity.
     *
     * @var class-string<IHistoryTrackable>|string
     */
    protected const string CLASS_NAME = '';

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly TranslatorInterface $translator
    ) {
        if (static::CLASS_NAME === '') {
            throw HistoryException::historyFactoryHasNotDefinedClassNameConstant(static::class);
        }
    }

    public function createFromPersistEvent(
        UserInterface $user,
        IHistoryTrackable $historyTrackableEntity,
        array $changes,
    ): IHistory {

    }

    public function createFromUpdateEvent(
        UserInterface $user,
        IHistoryTrackable $historyTrackableEntity,
        array $changes,
    ): IHistory {

    }

    abstract public function supports(IHistoryTrackable $historyTrackableEntity): bool;

    abstract protected function createHistoryEntity(
        ?UserInterface $user,
        ?string $message,
        HistorySeverityEnum $severity,
        array $changes,
        IHistoryTrackable $historyTrackableEntity,
    ): IHistory;
}