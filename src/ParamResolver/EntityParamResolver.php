<?php declare(strict_types = 1);

namespace JtcSolutions\Core\ParamResolver;

use Exception;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Service\RepositoryLocator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class EntityParamResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly RepositoryLocator $repositoryLocator,
    ) {
    }

    /**
     * @return iterable<IEntity>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (
            $argument->getType() === null
            || ! is_subclass_of($argument->getType(), IEntity::class)
        ) {
            return null;
        }

        $repository = $this->repositoryLocator->locate($argument->getType());

        $parameterName = $argument->getName();
        $parameterValue = $request->get($parameterName);

        if (! is_string($parameterValue)) {
            throw new Exception('Invalid query parameter value.');
        }

        $uuid = Uuid::fromString($parameterValue);

        // this throws entity not found even if you do not have rights and should throw 403
        // this might be something we want to solve ?
        // TODO: check this
        yield $repository->find($uuid);
    }
}
