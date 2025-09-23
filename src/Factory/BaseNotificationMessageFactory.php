<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Factory;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class BaseNotificationMessageFactory
{
    protected MessageBusInterface $messageBus;

    #[Required]
    public function setMessageBus(MessageBusInterface $messageBus): void
    {
        $this->messageBus = $messageBus;
    }
}
