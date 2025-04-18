<?php declare(strict_types = 1);

namespace JtcSolutions\Core\ParamResolver;

use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class UuidQueryParamResolver implements ValueResolverInterface
{
    /**
     * @return iterable<UuidInterface|null>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== UuidInterface::class) {
            return [];
        }

        $parameterName = $argument->getName();
        $parameterValue = $request->get($parameterName);

        if ($parameterValue !== null && ! is_string($parameterValue)) {
            throw new BadRequestHttpException(
                sprintf('Query parameter "%s" must be a string UUID.', $parameterName),
            );
        }

        try {
            if ($parameterValue === null) {
                if ($argument->getDefaultValue() === null) {
                    yield null;
                } else {
                    if (! is_string($argument->getDefaultValue())) {
                        throw new BadRequestHttpException('Invalid query parameter default value.');
                    }
                    /**
                     * @psalm-suppress InvalidReturnStatement
                     */
                    return Uuid::fromString($argument->getDefaultValue());
                }
            } else {
                yield Uuid::fromString($parameterValue);
            }
        } catch (InvalidUuidStringException $e) {
            throw new BadRequestHttpException('Invalid query parameter value.');
        }
    }
}
