<?php declare(strict_types=1);

namespace Qnix\RESTful\Infrastructure\Transformer\Exception;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ApiValidationFieldException extends Exception
{
    public function __construct(
        private readonly array $errors,
        ?Throwable $previous = null,
    ) {
        parent::__construct(code: Response::HTTP_UNPROCESSABLE_ENTITY, previous: $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}