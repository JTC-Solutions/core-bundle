<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Service\PropertyDescriber;

use Nelmio\ApiDocBundle\PropertyDescriber\PropertyDescriberInterface;
use OpenApi\Annotations\Schema;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\PropertyInfo\Type;

class UuidInterfacePropertyDescriber implements PropertyDescriberInterface
{
    public function describe(array $types, Schema $property, ?array $context = null): void
    {
        $property->type = 'string';
        $property->format = 'uuid';
    }

    public function supports(array $types, array $context = []): bool
    {
        return \count($types) === 1
            && $types[0]->getBuiltinType() === Type::BUILTIN_TYPE_OBJECT
            && $types[0]->getClassName() !== null
            && is_a($types[0]->getClassName(), UuidInterface::class, true);
    }
}
