<?php declare(strict_types=1);

namespace Qnix\RESTful\Infrastructure\Transformer\Exception;

abstract class AbstractRequestException extends \Exception
{
    protected bool $isProcessed = false;

    public function isProcessed(): bool
    {
        return $this->isProcessed;
    }
}