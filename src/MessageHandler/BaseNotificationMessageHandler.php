<?php declare(strict_types = 1);

namespace JtcSolutions\Core\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use JtcSolutions\Core\Message\NotificationMessage;
use Symfony\Contracts\Service\Attribute\Required;

abstract class BaseNotificationMessageHandler
{
    protected EntityManagerInterface $entityManager;

    /**
     * This method subscribes to the message and creates a notification entity.
     * This entity created should extend the BaseNotification class.
     */
    abstract public function __invoke(NotificationMessage $message): void;

    #[Required]
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }
}
