<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Service;

use JtcSolutions\Core\Dto\IEntityRequestBody;
use JtcSolutions\Core\Entity\IEntity;
use Ramsey\Uuid\UuidInterface;

/**
 * @template TEntity of IEntity
 * @template TRequestBody of IEntityRequestBody
 */
interface IEntityService
{
    /**
     * @param TRequestBody $requestBody
     * @return TEntity
     */
    public function handleCreate(IEntityRequestBody $requestBody): IEntity;

    /**
     * @param TRequestBody $requestBody
     * @return TEntity
     */
    public function handleUpdate(UuidInterface $entityId, IEntityRequestBody $requestBody): IEntity;

    /**
     * @param TEntity|UuidInterface $entityId
     */
    public function handleDelete(UuidInterface|IEntity $entityId): void;
}
