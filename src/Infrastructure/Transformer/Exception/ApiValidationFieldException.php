<?php declare(strict_types=1);

namespace Qnix\RESTful\Infrastructure\Transformer\Exception;

use Symfony\Component\HttpFoundation\Response;

final class ApiValidationFieldException extends \Exception
{
    public function __construct(
        private readonly array $errors,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(code: Response::HTTP_UNPROCESSABLE_ENTITY, previous: $previous);
    }

    /**
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}