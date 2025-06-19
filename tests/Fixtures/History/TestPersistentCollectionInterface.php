<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\History;

/**
 * Interface for testing PersistentCollection behavior
 */
interface TestPersistentCollectionInterface
{
    public function getOwner(): object;

    /**
     * @return array<string, mixed>
     */
    public function getMapping(): array;

    /**
     * @return array<int, object>
     */
    public function getInsertDiff(): array;

    /**
     * @return array<int, object>
     */
    public function getDeleteDiff(): array;
}
