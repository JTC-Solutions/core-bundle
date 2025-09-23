<?php declare(strict_types = 1);

namespace JtcSolutions\Core\ParamResolver;

use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Resolves controller action arguments that are type-hinted with UuidInterface.
 * It reads a query parameter whose name matches the argument name and attempts
 * to convert its value into a UuidInterface object.
 * Handles nullable arguments and default values.
 */
class UuidQueryParamResolver implements ValueResolverInterface
{
    /**
     * Attempts to resolve a controller argument type-hinted with UuidInterface from a query parameter.
     *
     * @param Request $request The current request object.
     * @param ArgumentMetadata $argument Metadata about the controller argument being resolved.
     * @return iterable<UuidInterface|null> An iterable containing the resolved UuidInterface object, null (if allowed), or empty if not applicable.
     * @throws BadRequestHttpException If the query parameter value exists but is not a string,
     *                                 or if the value is not a valid UUID string,
     *                                 or if a non-string default value is provided for the argument.
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== UuidInterface::class) {
            return [];
        }

        $parameterName = $argument->getName();
        /** @phpstan-ignore-next-line */
        $parameterValue = $request->get($parameterName);

        if ($parameterValue !== null && ! is_string($parameterValue)) {
            throw new BadRequestHttpException(
                sprintf('Query parameter "%s" must be a string UUID, received type "%s".', $parameterName, gettype($parameterValue)),
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
