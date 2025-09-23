<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Exception;

use JtcSolutions\Core\Entity\IEntity;
use Symfony\Component\HttpFoundation\Response;

class NotificationException extends TranslatableException
{
    /**
     * @param int<0, max> $code
     */
    protected function __construct(
        string $message,
        string $translationCode,
        int $code = Response::HTTP_BAD_REQUEST,
    ) {
        parent::__construct($message, $code, $translationCode);
    }

    public static function notificationNotFound(IEntity $user, IEntity $notification): self
    {
        return new self(
            sprintf('Notification %s not found for user %s', $notification->getId(), $user->getId()),
            'notification.not_found',
            Response::HTTP_NOT_FOUND,
        );
    }

    public static function notificationIsAlreadyMarkedAsRead(IEntity $user, IEntity $notification): self
    {
        return new self(
            sprintf('Notification %s is already marked as read for user %s', $notification->getId(), $user->getId()),
            'notification.already_marked_as_read',
            Response::HTTP_BAD_REQUEST,
        );
    }
}
