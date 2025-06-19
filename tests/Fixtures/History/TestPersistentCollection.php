<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

use Doctrine\ORM\PersistentCollection;

/**
 * Test implementation for PersistentCollection simulation
 * Since PersistentCollection is final in Doctrine 3.x, we create a test double
 */
class TestPersistentCollection implements TestPersistentCollectionInterface
{
    private object $owner;

    /**
     * @var array<string, mixed>
     */
    private array $mapping;

    /**
     * @var array<int, object>
     */
    private array $insertDiff;

    /**
     * @var array<int, object>
     */
    private array $deleteDiff;

    /**
     * @param array<string, mixed> $mapping
     * @param array<int, object> $insertDiff
     * @param array<int, object> $deleteDiff
     */
    public function __construct(
        object $owner,
        array $mapping,
        array $insertDiff = [],
        array $deleteDiff = [],
    ) {
        $this->owner = $owner;
        $this->mapping = $mapping;
        $this->insertDiff = $insertDiff;
        $this->deleteDiff = $deleteDiff;
    }

    public function getOwner(): object
    {
        return $this->owner;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    /**
     * @return array<int, object>
     */
    public function getInsertDiff(): array
    {
        return $this->insertDiff;
    }

    /**
     * @return array<int, object>
     */
    public function getDeleteDiff(): array
    {
        return $this->deleteDiff;
    }
}
