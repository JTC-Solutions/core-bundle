<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Entity;

use DateTimeImmutable;
use JtcSolutions\Core\Dto\HistoryChange;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use Symfony\Component\Security\Core\User\UserInterface;

interface IHistory
{
    /** @return non-empty-string|null */
    public function getMessage(): ?string;

    public function getSeverity(): HistorySeverityEnum;

    /** @return HistoryChange[] */
    public function getChanges(): array;

    public function getCreatedBy(): ?UserInterface;

    public function getCreatedAt(): DateTimeImmutable;
}
