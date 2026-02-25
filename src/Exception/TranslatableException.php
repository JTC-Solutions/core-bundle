<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Exception;

use Exception;
use Throwable;

/**
 * Base class for exceptions that are intended to be translated into user-friendly messages
 * and associated with a specific HTTP status code for API responses.
 *
 * Subclasses should typically provide static factory methods for instantiation
 * and define specific translation keys and default status codes.
 */
abstract class TranslatableException extends Exception
{
    /**
     * Protected constructor for TranslatableException and its subclasses.
     *
     * @param string $message The detailed, internal exception message (primarily for logging).
     * @param int<0, max> $statusCode The HTTP status code that should be associated with this exception in API responses.
     * @param string $translationCode The translation key used by the Symfony Translator to fetch a user-friendly message.
     * @param array<string, string|int> $translationParameters Parameters to be passed to the translator for interpolation into the message.
     * @param Throwable|null $previous The previous throwable used for the exception chaining.
     */
    protected function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $translationCode,
        private readonly array $translationParameters = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Gets the HTTP status code associated with this exception.
     *
     * @return int<0, max> The HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Gets the translation key for retrieving a localized error message.
     *
     * @return string The translation key (e.g., 'entity_not_found').
     */
    public function getTranslationCode(): string
    {
        return $this->translationCode;
    }

    /**
     * Gets the parameters required for translating the error message.
     *
     * @return array<string, string|int> An associative array of parameters for the translator.
     */
    public function getTranslationParameters(): array
    {
        $wrapped = [];

        foreach ($this->translationParameters as $key => $value) {
            if (! str_starts_with($key, '%') || ! str_ends_with($key, '%')) {
                $key = "%{$key}%";
            }

            $wrapped[$key] = $value;
        }

        return $wrapped;
    }
}
