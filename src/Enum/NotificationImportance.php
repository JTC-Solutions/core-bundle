<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Enum;

enum NotificationImportance: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
