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
     * @param array<string, mixed> $context
     */
    protected function delete(UuidInterface|IEntity $id, array $context = []): void
    {
        if ($id instanceof DummyEntity) {
            $id = $id->getId();
        }

        $entity = $this->ensureEntityExists(['id' => $id]);

        // Log context information if provided
        if (isset($context['log_message'])) {
            $this->logger->info($context['log_message'], ['entity_id' => $id->toString()]);
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function mapDataAndCallUpdate(UuidInterface|IEntity $entityId, IEntityRequestBody $requestBody, array $context = []): IEntity
    {
        // Log context information if provided
        if (isset($context['log_message'])) {
            $this->logger->info($context['log_message'], [
                'entity_id' => $entityId instanceof IEntity ? $entityId->getId()->toString() : $entityId->toString(),
                'operation' => 'update',
            ]);
        }

        return $this->update(
            id: $entityId,
            string: $requestBody->string,
            integer: $requestBody->integer,
            float: $requestBody->float,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function mapDataAndCallCreate(IEntityRequestBody $requestBody, array $context = []): IEntity
    {
        // Log context information if provided
        if (isset($context['log_message'])) {
            $this->logger->info($context['log_message'], [
                'operation' => 'create',
                'data' => [
                    'string' => $requestBody->string,
                    'integer' => $requestBody->integer,
                    'float' => $requestBody->float,
                ],
            ]);
        }

        return $this->create(
            string: $requestBody->string,
            integer: $requestBody->integer,
            float: $requestBody->float,
        );
    }
}
