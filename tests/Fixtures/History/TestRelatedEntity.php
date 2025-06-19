<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\ILabelable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class TestRelatedEntity implements IEntity, ILabelable
{
    private UuidInterface $id;

    /**
     * @var non-empty-string
     */
    private string $title;

    /**
     * @var non-empty-string
     */
    private string $code;

    /**
     * @param non-empty-string $title
     * @param non-empty-string $code
     */
    public function __construct(string $title = 'Test Related', string $code = 'REL001')
    {
        $this->id = Uuid::uuid4();
        $this->title = $title;
        $this->code = $code;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param non-empty-string $title
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param non-empty-string $code
     */
    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getLabel(): string
    {
        return sprintf('%s (%s)', $this->title, $this->code);
    }
}
