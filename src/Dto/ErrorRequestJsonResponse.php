<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Represents a standardized JSON response for request errors (e.g., validation failures).
 * Extends Symfony's JsonResponse to provide a structured error format.
 */
class ErrorRequestJsonResponse extends JsonResponse
{
    /**
     * Constructs a new ErrorRequestJsonResponse.
     *
     * The response body will contain 'title', 'message', and 'errors' keys.
     *
     * @param string $title A short, descriptive title for the error (e.g., "Validation Failed").
     * @param string $message A more detailed explanation of the error.
     * @param string[] $errors An array of specific error details, often validation messages. Defaults to an empty array.
     * @param int $statusCode The HTTP status code for the response. Defaults to 400 (Bad Request).
     */
    public function __construct(
        public string $title,
        public string $message,
        public array $errors = [],
        public int $statusCode = Response::HTTP_BAD_REQUEST,
    ) {
        parent::__construct(
            [
                'title' => $this->title,
                'message' => $this->message,
                'errors' => $this->errors,
            ],
            $this->statusCode,
        );
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
