<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\Dummy\Dto;

use JtcSolutions\Core\Dto\IEntityRequestBody;

final readonly class DummyCreateRequest implements IEntityRequestBody
{
    public function __construct(
        public string $string,
        public int $integer,
        public float $float,
    ) {
    }
}
