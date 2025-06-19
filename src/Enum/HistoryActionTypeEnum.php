<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Enum;

enum HistoryActionTypeEnum: string
{
    case CREATE = 'created';
    case UPDATE = 'update';
    case CHANGE_RELATION = 'change-relation';
    case REMOVED_FROM_COLLECTION = 'removed';
    case ADDED_TO_COLLECTION = 'added';
    case PIVOT_CREATED = 'pivot_created';
    case PIVOT_UPDATED = 'pivot_updated';
    case PIVOT_DELETED = 'pivot_deleted';
}
