<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Service\Serializer;

use Ramsey\Uuid\UuidInterface;

class SerializerCircularReferenceHandler
{
    public static function handleCircularReference(object $object): string
    {
        if (method_exists($object, 'getId')) {
            $id = $object->getId();

            if (is_string($id)) {
                return $id;
            }

            if ($id instanceof UuidInterface) {
                return $id->toString();
            }
        }

        return spl_object_hash($object); // Fallback to object hash
    }
}
