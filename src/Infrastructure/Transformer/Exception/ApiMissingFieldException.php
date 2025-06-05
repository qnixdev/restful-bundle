<?php declare(strict_types=1);

namespace Qnix\RESTful\Infrastructure\Transformer\Exception;

use Symfony\Component\HttpFoundation\Response;

final class ApiMissingFieldException extends \Exception
{
    public function __construct(
        string $field,
        ?\Throwable $previous = null,
    ) {
        parent::__construct("Field '$field' is required.", Response::HTTP_UNPROCESSABLE_ENTITY, $previous);
    }
}