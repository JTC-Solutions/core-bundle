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

/**
 * Listens for exceptions thrown during the request lifecycle (kernel.exception event).
 * Its primary goal is to catch various exceptions and convert them into a standardized,
 * translatable JSON error response (ErrorRequestJsonResponse) for API consumers.
 * It handles custom TranslatableException types, common framework/Doctrine exceptions,
 * and falls back to generic responses based on HTTP status codes.
 */
final class ExceptionListener
{
    /**
     * @param TranslatorInterface $translator Used to translate error messages based on keys.
     * @param LoggerInterface $logger Used to log the original exceptions for debugging.
     * @param string $env The application environment ('dev', 'prod', etc.), used for the debug check.
     * @param string $exceptionTranslationDomain The translation domain where exception messages are defined.
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'APP_ENV')]
        private readonly string $env,
        private readonly string $exceptionTranslationDomain,
    ) {
    }

    /**
     * Handles the kernel.exception event.
     * Logs the exception, checks for a debug flag, and attempts to create a structured
     * JSON response based on the exception type or its HTTP status code.
     *
     * @param ExceptionEvent $event The event object containing the request and the thrown exception.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        // Always log the original exception regardless of response type
        $throwable = $event->getThrowable();
        $this->logger->error(
            sprintf('Exception caught by listener: %s', $throwable->getMessage()),
            ['exception' => $throwable], // Log the full exception object for context
        );

        $request = $event->getRequest();
        $debug = $request->headers->get('debug', 'false');

        // Skip listener logic if debug header is set and not in production environment
        if ($debug === 'true' && $this->env !== 'prod') {
            $this->logger->info('ExceptionListener bypassed due to debug header in non-prod environment.');
            return;
        }

        // Priority 1: Handle custom TranslatableExceptions (including previous exceptions)
        if ($this->handleTranslatableExceptions($event)) {
            return; // Response set by handler
        }

        // Priority 2: Handle specific known exceptions
        if ($throwable instanceof ValidationFailedException) {
            $response = $this->createResponse(Response::HTTP_UNPROCESSABLE_ENTITY, 'unprocessable_content');
            $event->setResponse($response);
            return;
        }

        if ($throwable instanceof EntityNotFoundException) {
            // Note: This catches Doctrine's EntityNotFoundException directly.
            // If our custom JtcSolutions\Core\Exception\EntityNotFoundException is thrown,
            // it should be caught by handleTranslatableExceptions first.
            $response = $this->createResponse(Response::HTTP_NOT_FOUND, 'not_found');
            $event->setResponse($response);
            return;
        }

        if ($event->getThrowable() instanceof InvalidArgumentException) {
            $response = $this->createResponse(Response::HTTP_BAD_REQUEST, 'bad_request');
            $event->setResponse($response);
            return;
        }

        // Priority 3: Fallback based on HttpExceptionInterface status code or generic 500
        $this->handleFallbackResponse($event);
    }

    /**
     * Handles exceptions that are instances of HttpExceptionInterface or provides a default 500 response.
     * It maps standard HTTP status codes to translation keys for generating the response.
     *
     * @param ExceptionEvent $event The exception event.
     */
    private function handleFallbackResponse(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $code = Response::HTTP_INTERNAL_SERVER_ERROR; // Default to 500
        $translationKey = 'internal_server_error';

        if ($throwable instanceof HttpExceptionInterface) {
            $code = $throwable->getStatusCode();
        }

        switch ($code) {
            case Response::HTTP_BAD_REQUEST:
                $translationKey = 'bad_request';
                break;
            case Response::HTTP_UNAUTHORIZED:
                $translationKey = 'unauthorized';
                break;
            case Response::HTTP_FORBIDDEN:
                $translationKey = 'forbidden';
                break;
            case Response::HTTP_NOT_FOUND:
                $translationKey = 'not_found';
                break;
            case Response::HTTP_METHOD_NOT_ALLOWED:
                $translationKey = 'method_not_allowed';
                break;
            case Response::HTTP_CONFLICT:
                $translationKey = 'conflict';
                break;
            case Response::HTTP_UNPROCESSABLE_ENTITY:
                $translationKey = 'unprocessable_entity';
                break;
            case Response::HTTP_TOO_MANY_REQUESTS:
                $translationKey = 'too_many_requests';
                break;
            default:
                // Keep default $code = 500 and $translationKey = 'internal_server_error'
                // Log unexpected status codes or generic exceptions more verbosely if desired
                if ($code === Response::HTTP_INTERNAL_SERVER_ERROR) {
                    $this->logger->error(
                        'Fallback response triggered for unhandled exception or 500 error.',
                        ['exception_message' => $throwable->getMessage()],
                    );
                }
                break;
        }

        $response = $this->createResponse($code, $translationKey);
        $event->setResponse($response);
    }

    /**
     * Checks if the exception or its previous exception is a TranslatableException
     * and sets the response accordingly.
     *
     * @param ExceptionEvent $event The exception event.
     * @return bool True if a response was set (exception was handled), false otherwise.
     */
    private function handleTranslatableExceptions(ExceptionEvent $event): bool
    {
        $exception = $event->getThrowable();
        $translatableException = null;

        if ($exception instanceof TranslatableException) {
            $translatableException = $exception;
        } else {
            $previous = $exception->getPrevious();
            if ($previous instanceof TranslatableException) {
                // Handle cases where the TranslatableException is wrapped
                $translatableException = $previous;
                $this->logger->info(
                    'Handling TranslatableException found in previous exception.',
                    ['original_exception' => get_class($exception), 'translatable_exception' => get_class($translatableException)],
                );
            }
        }


        if ($translatableException !== null) {
            $response = $this->createResponseFromCustomException($translatableException);
            $event->setResponse($response);
            return true;
        }

        return false;
    }

    /**
     * Creates a standardized error response from a TranslatableException instance.
     * Uses translation keys prefixed with "custom." derived from the exception's translation code.
     *
     * @param TranslatableException $e The exception instance.
     * @return ErrorRequestJsonResponse The formatted JSON response.
     */
    private function createResponseFromCustomException(
        TranslatableException $e,
    ): ErrorRequestJsonResponse {
        $titleKey = "custom.{$e->getTranslationCode()}.title";
        $messageKey = "custom.{$e->getTranslationCode()}.message";

        return new ErrorRequestJsonResponse(
            title: $this->translator->trans($titleKey, $e->getTranslationParameters(), $this->exceptionTranslationDomain),
            message: $this->translator->trans($messageKey, $e->getTranslationParameters(), $this->exceptionTranslationDomain),
            // errors: [],
            statusCode: $e->getStatusCode(),
        );
    }

    /**
     * Creates a standardized error response for a given HTTP status code and type identifier.
     * Uses translation keys prefixed with "core." derived from the $httpCodeType.
     *
     * @param int $statusCode The HTTP status code (e.g., 400, 404, 500).
     * @param string $httpCodeType A string identifier for the status code (e.g., 'bad_request', 'not_found').
     * @return ErrorRequestJsonResponse The formatted JSON response.
     */
    private function createResponse(
        int $statusCode,
        string $httpCodeType,
    ): ErrorRequestJsonResponse {
        $titleKey = "core.{$httpCodeType}.title";
        $messageKey = "core.{$httpCodeType}.message";

        return new ErrorRequestJsonResponse(
            title: $this->translator->trans($titleKey, [], $this->exceptionTranslationDomain),
            message: $this->translator->trans($messageKey, [], $this->exceptionTranslationDomain),
            // errors: [], // Typically empty for generic HTTP errors
            statusCode: $statusCode,
        );
    }
}
