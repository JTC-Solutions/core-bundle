<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Exception;

use Exception;
use Throwable;

class TranslatableException extends Exception
{
    /**
     * @param array<string, string|int> $translationParameters
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

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getTranslationCode(): string
    {
        return $this->translationCode;
    }

    /**
     * @return array<string, string|int>
     */
    public function getTranslationParameters(): array
    {
        return $this->translationParameters;
    }
}
