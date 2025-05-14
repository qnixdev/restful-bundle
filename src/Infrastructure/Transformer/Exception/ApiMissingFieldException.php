<?php declare(strict_types=1);

namespace Qnix\RESTful\Infrastructure\Transformer\Exception;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ApiMissingFieldException extends Exception
{
    public function __construct(
        string $field,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf("Field '%s' is required.", $field), Response::HTTP_UNPROCESSABLE_ENTITY, $previous);
    }
}