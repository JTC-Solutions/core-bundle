<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\Dummy\Service;

use Doctrine\ORM\EntityManagerInterface;
use JtcSolutions\Core\Dto\IEntityRequestBody;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Service\BaseCRUDEntityService;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Dto\DummyCreateRequest;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Entity\DummyEntity;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Repository\DummyRepository;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/** @extends BaseCRUDEntityService<DummyEntity, DummyCreateRequest> */
class DummyCRUDEntityService extends BaseCRUDEntityService
{
    public function __construct(
        DummyRepository $repository,
        protected readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($repository);
    }

    public function create(
        string $string,
        int $integer,
        float $float,
        string $contextString,
    ): DummyEntity {
        $entity = new DummyEntity(
            id: Uuid::uuid4(),
            string: $string,
            integer: $integer,
            float: $float,
            contextString: $contextString,
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
        string $contextString,
    ): DummyEntity {
        if (! $id instanceof DummyEntity) {
            $entity = $this->ensureEntityExists(['id' => $id]);
        } else {
            $entity = $id;
        }

        $entity->setString($string);
        $entity->setFloat($float);
        $entity->setInteger($integer);
        $entity->setContextString($contextString);

        $this->entityManager->flush();

        return $entity;
    }

    /**
     * @param DummyEntity|UuidInterface $id
     * @param array<string, mixed> $context
     */
    protected function delete(UuidInterface|IEntity $id, array $context = []): void
    {
        if ($id instanceof DummyEntity) {
            $id = $id->getId();
        }

        $entity = $this->ensureEntityExists(['id' => $id]);

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function mapDataAndCallUpdate(UuidInterface|IEntity $entityId, IEntityRequestBody $requestBody, array $context = []): IEntity
    {
        return $this->update(
            id: $entityId,
            string: $requestBody->string,
            integer: $requestBody->integer,
            float: $requestBody->float,
            contextString: $context['contextString'],
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function mapDataAndCallCreate(IEntityRequestBody $requestBody, array $context = []): IEntity
    {
        return $this->create(
            string: $requestBody->string,
            integer: $requestBody->integer,
            float: $requestBody->float,
            contextString: $context['contextString'],
        );
    }
}
