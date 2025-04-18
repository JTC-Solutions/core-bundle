<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\Concrete\Dto;

use JtcSolutions\Core\Dto\IEntityRequestBody;

class TestRequestBody implements IEntityRequestBody
{
    public function __construct(
        public string $data = 'test',
    ) {
    }
}
