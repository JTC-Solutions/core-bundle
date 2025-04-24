<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\Dummy\Service;

use Doctrine\ORM\EntityManagerInterface;
use JtcSolutions\Core\Dto\IEntityRequestBody;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Service\BaseCRUDEntityService;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Dto\DummyCreateRequest;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Entity\DummyEntity;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Repository\DummyRepository;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/** @extends BaseCRUDEntityService<DummyEntity, DummyCreateRequest> */
class DummyCRUDEntityService extends BaseCRUDEntityService
{
    public function __construct(
        DummyRepository $repository,
        LoggerInterface $logger,
        protected readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($repository, $logger);
    }

    public function create(
        string $string,
        int $integer,
        float $float,
    ): DummyEntity {
        $entity = new DummyEntity(
            id: Uuid::uuid4(),
            string: $string,
            integer: $integer,
            float: $float,
        );

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    /**
     * @param DummyEntity|UuidInterface $id
     */
    public function update(
        UuidInterface|IEntity $id,
        string $string,
        int $integer,
        float $float,
    ): DummyEntity {
        if (! $id instanceof DummyEntity) {
            $entity = $this->ensureEntityExists(['id' => $id]);
        } else {
            $entity = $id;
        }

        $entity->setString($string);
        $entity->setFloat($float);
        $entity->setInteger($integer);

        $this->entityManager->flush();

        return $entity;
    }

    /**
     * @param DummyEntity|UuidInterface $id
     */
    protected function delete(UuidInterface|IEntity $id): void
    {
        if ($id instanceof DummyEntity) {
            $id = $id->getId();
        }

        $entity = $this->ensureEntityExists(['id' => $id]);

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    protected function mapDataAndCallUpdate(UuidInterface|IEntity $entityId, IEntityRequestBody $requestBody): IEntity
    {
        return $this->update(
            id: $entityId,
            string: $requestBody->string,
            integer: $requestBody->integer,
            float: $requestBody->float,
        );
    }

    protected function mapDataAndCallCreate(IEntityRequestBody $requestBody): IEntity
    {
        return $this->create(
            string: $requestBody->string,
            integer: $requestBody->integer,
            float: $requestBody->float,
        );
    }
}
