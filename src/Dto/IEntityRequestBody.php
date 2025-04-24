<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto;

use JtcSolutions\Core\Controller\BaseEntityCRUDController;
use JtcSolutions\Core\Service\BaseCRUDEntityService;

/**
 * Marker interface for Data Transfer Objects (DTOs) that represent the body
 * of a request used for creating or updating an entity.
 *
 * Implementing this interface signifies that a class carries data intended
 * for entity persistence operations, often used in conjunction with validation
 * and services like those in BaseEntityCRUDController.
 *
 * @see BaseEntityCRUDController
 * @see BaseCRUDEntityService
 */
interface IEntityRequestBody
{
}
