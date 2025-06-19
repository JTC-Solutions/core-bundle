<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

enum TestStatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
}
