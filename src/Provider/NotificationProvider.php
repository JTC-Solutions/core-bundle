<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Provider;

use JtcSolutions\Core\Dto\Notification\NotificationResponse;
use JtcSolutions\Core\Dto\Notification\NotificationView;
use JtcSolutions\Core\Entity\BaseNotificationList;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Factory\EntityLinkFactory;
use JtcSolutions\Core\Factory\PaginationFactory;
use JtcSolutions\Core\Repository\INotificationListRepository;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @template TNotificationList of BaseNotificationList */
class NotificationProvider
{
    protected readonly TranslatorInterface $translator;

    protected readonly EntityLinkFactory $entityLinkFactory;

    /**
     * @param INotificationListRepository<TNotificationList> $notificationListRepository
     */
    public function __construct(
        protected readonly INotificationListRepository $notificationListRepository,
    ) {
    }

    #[Required]
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    #[Required]
    public function setEntityLinkFactory(EntityLinkFactory $entityLinkFactory): void
    {
        $this->entityLinkFactory = $entityLinkFactory;
    }

    /**
     * @param int<1, max> $limit
     * @param int<0, max> $offset
     */
    public function provide(
        IEntity $currentUser,
        int $limit,
        int $offset,
    ): NotificationResponse {
        $userNotifications = $this->notificationListRepository->findByUser($currentUser, $limit, $offset);

        /** @var int<0, max> $total */
        $total = $this->notificationListRepository->countByUser($currentUser);

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

        $pagination = PaginationFactory::create($total, $offset, $limit);

        return new NotificationResponse(
            data: $translated,
            metadata: $pagination,
        );
    }
}
