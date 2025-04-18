<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Listener;

use Doctrine\ORM\EntityNotFoundException;
use InvalidArgumentException;
use JtcSolutions\Core\Dto\ErrorRequestJsonResponse;
use JtcSolutions\Core\Exception\TranslatableException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ExceptionListener
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'APP_ENV')]
        private readonly string $env,
        private readonly string $exceptionTranslationDomain,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $this->logger->error($event->getThrowable());

        $request = $event->getRequest();
        $debug = $request->headers->get('debug', 'false');

        // disable when header is debug=true and not in production
        if ($debug === 'true' && $this->env !== 'prod') {
            return;
        }

        if ($this->handleTranslatableExceptions($event) === true) {
            return;
        }

        if ($event->getThrowable() instanceof ValidationFailedException) {
            $response = $this->createResponse(Response::HTTP_UNPROCESSABLE_ENTITY, 'unprocessable_content');
            $event->setResponse($response);
            return;
        }

        if ($event->getThrowable() instanceof EntityNotFoundException) {
            $response = $this->createResponse(Response::HTTP_NOT_FOUND, 'not_found');
            $event->setResponse($response);
            return;
        }

        if ($event->getThrowable() instanceof InvalidArgumentException) {
            $response = $this->createResponse(Response::HTTP_BAD_REQUEST, 'bad_request');
            $event->setResponse($response);
            return;
        }

        // default exception message based on http code
        $this->handleFallbackResponse($event);
    }

    private function handleFallbackResponse(ExceptionEvent $event): void
    {
        $code = 500;
        if ($event->getThrowable() instanceof HttpExceptionInterface) {
            $code = $event->getThrowable()->getStatusCode();
        }

        switch ($code) {
            case Response::HTTP_BAD_REQUEST:
                $response = $this->createResponse($code, 'bad_request');
                $event->setResponse($response);
                return;
            case Response::HTTP_UNAUTHORIZED:
                $response = $this->createResponse($code, 'unauthorized');
                $event->setResponse($response);
                return;
            case Response::HTTP_FORBIDDEN:
                $response = $this->createResponse($code, 'forbidden');
                $event->setResponse($response);
                return;
            case Response::HTTP_NOT_FOUND:
                $response = $this->createResponse($code, 'not_found');
                $event->setResponse($response);
                return;
            case Response::HTTP_METHOD_NOT_ALLOWED:
                $response = $this->createResponse($code, 'method_not_allowed');
                $event->setResponse($response);
                return;
            case Response::HTTP_CONFLICT:
                $response = $this->createResponse($code, 'conflict');
                $event->setResponse($response);
                return;
            case Response::HTTP_UNPROCESSABLE_ENTITY:
                $response = $this->createResponse($code, 'unprocessable_entity');
                $event->setResponse($response);
                return;
            case Response::HTTP_TOO_MANY_REQUESTS:
                $response = $this->createResponse($code, 'too_many_requests');
                $event->setResponse($response);
                return;
            default:
                $exception = $event->getThrowable();
                $response = $this->createResponse(500, 'internal_server_error');
                $this->logger->error($exception->getMessage(), ['exception' => $exception]);
                $event->setResponse($response);
        }
    }

    // returns true if response is set and false if it is not translatable exception
    private function handleTranslatableExceptions(ExceptionEvent $event): bool
    {
        $response = null;
        $exception = $event->getThrowable();
        $previous = $exception->getPrevious();

        if ($exception instanceof TranslatableException) {
            $response = $this->createResponseFromCustomException($exception);
        } elseif ($previous instanceof TranslatableException) {
            $response = $this->createResponseFromCustomException($previous);
        }

        if ($response !== null) {
            $event->setResponse($response);
            return true;
        }

        return false;
    }

    private function createResponseFromCustomException(
        TranslatableException $e,
    ): ErrorRequestJsonResponse {
        return new ErrorRequestJsonResponse(
            title: $this->translator->trans("custom.{$e->getTranslationCode()}.title", [], $this->exceptionTranslationDomain),
            message: $this->translator->trans("custom.{$e->getTranslationCode()}.message", [], $this->exceptionTranslationDomain),
            statusCode: $e->getStatusCode(),
        );
    }

    private function createResponse(
        int $statusCode,
        string $httpCodeType, // such as bad_request
    ): ErrorRequestJsonResponse {
        return new ErrorRequestJsonResponse(
            title: $this->translator->trans("core.{$httpCodeType}.title", [], $this->exceptionTranslationDomain),
            message: $this->translator->trans("core.{$httpCodeType}.message", [], $this->exceptionTranslationDomain),
            statusCode: $statusCode,
        );
    }
}
