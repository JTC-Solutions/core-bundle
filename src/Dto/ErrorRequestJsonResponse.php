<?php declare(strict_types = 1);

namespace JtcSolutions\Core\Dto;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ErrorRequestJsonResponse extends JsonResponse
{
    /**
     * @param string[] $errors
     */
    public function __construct(
        public string $title,
        public string $message,
        public array $errors = [],
        public int $statusCode = Response::HTTP_BAD_REQUEST,
    ) {
        parent::__construct(
            [
                'title' => $title,
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
