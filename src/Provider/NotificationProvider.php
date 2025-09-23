<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Provider;

use JtcSolutions\Core\Dto\Notification\NotificationGroup;
use JtcSolutions\Core\Dto\Notification\NotificationGroupedResponse;
use JtcSolutions\Core\Dto\Notification\NotificationResponse;
use JtcSolutions\Core\Dto\Notification\NotificationView;
use JtcSolutions\Core\Entity\BaseNotificationList;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Factory\EntityLinkFactory;
use JtcSolutions\Core\Factory\PaginationFactory;
use JtcSolutions\Core\Repository\INotificationListRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @template TNotificationList of BaseNotificationList */
class NotificationProvider
{
    /**
     * @param INotificationListRepository<TNotificationList> $notificationListRepository
     */
    public function __construct(
        protected readonly INotificationListRepository $notificationListRepository,
        protected readonly TranslatorInterface $translator,
        protected readonly EntityLinkFactory $entityLinkFactory,
    ) {
    }

    /**
     * @param int<1, max> $limit
     * @param int<0, max> $offset
     */
    public function provide(
        IEntity $currentUser,
        bool $unreadOnly,
        int $limit,
        int $offset,
    ): NotificationResponse {
        $data = $this->getTranslatedNotifications($currentUser, $unreadOnly, $limit, $offset);

        $pagination = PaginationFactory::create($data['total'], $offset, $limit);

        return new NotificationResponse(
            data: $data['notifications'],
            metadata: $pagination,
        );
    }

    /**
     * @param int<1, max> $limit
     * @param int<0, max> $offset
     */
    public function provideGrouped(
        IEntity $currentUser,
        bool $unreadOnly,
        int $limit,
        int $offset,
    ): NotificationGroupedResponse {
        $data = $this->getTranslatedNotifications($currentUser, $unreadOnly, $limit, $offset);

        $notifications = $data['notifications'];

        $grouped = [];
        foreach ($notifications as $notification) {
            $grouped[$notification->link->type][] = $notification;
        }

        $groups = [];
        foreach ($grouped as $type => $notifications) {
            $groups[] = new NotificationGroup(
                type: $type,
                total: count($notifications),
                data: $notifications,
            );
        }

        $pagination = PaginationFactory::create($data['total'], $offset, $limit);

        return new NotificationGroupedResponse(
            data: $groups,
            metadata: $pagination,
        );
    }

    /**
     * @param int<1, max> $limit
     * @param int<0, max> $offset
     *
     * @return array{notifications: NotificationView[], total: int<0, max>}
     */
    protected function getTranslatedNotifications(
        IEntity $currentUser,
        bool $unreadOnly,
        int $limit,
        int $offset,
    ): array {
        $userNotifications = $this->notificationListRepository->findByUser($currentUser, $unreadOnly, $limit, $offset);

        /** @var int<0, max> $total */
        $total = $this->notificationListRepository->countByUser($currentUser, $unreadOnly);

        $translated = [];
        foreach ($userNotifications as $userNotification) {
            $notification = $userNotification->getNotification();

            $translationKeys = array_combine(
                array_map(static fn (string $key) => "%{$key}%", array_keys($notification->getDetails())),
                array_values($notification->getDetails()),
            );

            $translated[] = new NotificationView(
                id: $notification->getId(),
                subject: $this->translator->trans($notification->getSubject(), $translationKeys, 'notifications'),
                content: $this->translator->trans($notification->getContent(), $translationKeys, 'notifications'),
                importance: $notification->getImportance(),
                link: $this->entityLinkFactory->create($notification),
                readAt: $userNotification->getReadAt(),
            );
        }

        return [
            'notifications' => $translated,
            'total' => $total,
        ];
    }
}
