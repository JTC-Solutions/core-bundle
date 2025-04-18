<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Tests\Fixtures\Dummy\Controller;

use JtcSolutions\Core\Controller\BaseEntityCRUDController;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Dto\DummyCreateRequest;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Entity\DummyEntity;
use JtcSolutions\Core\Tests\Fixtures\Dummy\Service\DummyEntityService;
use Symfony\Component\HttpFoundation\JsonResponse;

/** @extends BaseEntityCRUDController<DummyEntity, DummyCreateRequest, DummyEntityService> */
class DummyController extends BaseEntityCRUDController
{
    public function create(DummyCreateRequest $request): JsonResponse
    {
        return $this->handleCreate($request, ['dummy:detail', 'reference']);
    }
}
