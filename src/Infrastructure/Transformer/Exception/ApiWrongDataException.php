<?php declare(strict_types=1);

namespace Qnix\RESTful\Infrastructure\Transformer\Exception;

use Symfony\Component\HttpFoundation\Response;

final class ApiWrongDataException extends AbstractRequestException
{
    public function __construct(
        string $message,
        ?\Throwable $previous = null,
        bool $isProcessed = false,
    ) {
        $this->isProcessed = $isProcessed;

        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY, $previous);
    }
}