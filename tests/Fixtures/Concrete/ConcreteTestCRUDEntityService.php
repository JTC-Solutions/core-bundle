<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\Concrete;

use JtcSolutions\Core\Dto\IEntityRequestBody;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Repository\IEntityRepository;
use JtcSolutions\Core\Service\BaseCRUDEntityService;
use JtcSolutions\Core\Tests\Fixtures\Concrete\Entity\TestEntity;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class ConcreteTestCRUDEntityService extends BaseCRUDEntityService
{
    public function publicEnsureEntityDoesNotExist(array $params, ?UuidInterface $ignoredEntityId = null): void
    {
        parent::ensureEntityDoesNotExist($params, $ignoredEntityId);
    }

    public function publicEnsureEntityExists(array $params): IEntity
    {
        return parent::ensureEntityExists($params);
    }

    public function publicFindEntityById(UuidInterface $id, IEntityRepository $repository): IEntity
    {
        return parent::findEntityById($id, $repository);
    }

    public function publicUpdateCollection(array $entities, array $inputs, callable $addEntity, callable $removeEntity): void
    {
        parent::updateCollection($entities, $inputs, $addEntity, $removeEntity);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function delete(UuidInterface|IEntity $id, array $context = []): void
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function mapDataAndCallCreate(IEntityRequestBody $requestBody, array $context = []): IEntity
    {
        return new TestEntity(Uuid::uuid4());
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function mapDataAndCallUpdate(UuidInterface|IEntity $entityId, IEntityRequestBody $requestBody, array $context = []): IEntity
    {
        return new TestEntity($entityId);
    }
}
